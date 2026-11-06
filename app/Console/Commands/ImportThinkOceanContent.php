<?php

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Services\ContentWorkflow;
use App\Services\MediaLibrary;
use App\Support\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports the content of the original Think Ocean website (copy, photographs,
 * projects, news, partners and Live TV settings) into the CMS.
 *
 * Everything goes through the media library and the publishing workflow, so
 * revisions and the activity log record who imported it. Safe to re-run:
 * existing media, projects and articles are reused rather than duplicated.
 */
class ImportThinkOceanContent extends Command
{
    protected $signature = 'cms:import-think-ocean
                            {--user= : Email of the administrator or editor the import is recorded against}
                            {--draft : Save everything as drafts instead of publishing it}';

    protected $description = 'Import the original Think Ocean website content into the CMS';

    private const EMAIL = 'info@thinkocean.earth';

    private const TV_EMAIL = 'TV@thinkocean.earth';

    /** The five missions, from the Think Ocean presentation. Each becomes a project. */
    private const MISSIONS = [
        [
            'slug' => 'stop-at-source-river-plastic-innovation',
            'title' => 'Stop at Source: River Plastic Innovation',
            'short' => 'Stopping plastic at source',
            'icon' => 'sparkles',
            'image' => 'deck-boat',
            'location' => 'United Kingdom',
            'summary' => 'Developing boat innovation that stops plastic in rivers before it reaches the ocean, in partnership with the University of Derby.',
            'description' => '<p>Most ocean plastic travels there along rivers. Think Ocean is developing boat-based technology that catches plastic waste in rivers before it can reach the sea.</p><h2>How the technology works</h2><p>Think Ocean’s Stop at Source technology has three main components:</p><ul><li><strong>The ramps</strong> guide floating waste towards the system.</li><li><strong>The bubble wall</strong> uses air from a perforated tube to push waste towards the surface and the catchment area.</li><li><strong>The catchment device</strong> gathers the waste with rotating forks and a conveyor belt into a container, powered by a waterwheel and solar panels.</li></ul><h2>Partners</h2><p>The project is developed in partnership with the University of Derby, and is part of the European Commission’s Horizon Europe mission to restore our ocean and waters by 2030.</p>',
        ],
        [
            'slug' => 'no-to-plastic-bottles',
            'title' => 'No to Plastic Bottles',
            'short' => 'Fighting single-use plastic bottles',
            'icon' => 'heart',
            'image' => 'deck-bottle-display',
            'location' => 'United Kingdom',
            'summary' => 'Fighting single-use plastic bottles with reusable alternatives and education activities, including with Nottingham Trent University.',
            'description' => '<p>A single-use plastic bottle is useful for minutes, but harms the planet for hundreds of years. Our “No to plastic bottles” campaign invites people to become plastic free and choose a reusable bottle instead.</p><h2>What we do</h2><ul><li>Reusable Think Ocean bottles that keep drinks cold or hot for up to 12 hours.</li><li>Co-branded bottles and campaigns with partners such as Nottingham Trent University.</li><li>Education activities that explain the impact of single-use plastic on the ocean and marine life.</li></ul><p>Join Think Ocean CIC in the fight against single-use plastic bottles.</p>',
        ],
        [
            'slug' => 'plastic-free-campus',
            'title' => 'Plastic Free Campus',
            'short' => 'Plastic-free campuses',
            'icon' => 'academic-cap',
            'image' => 'deck-plastic-free-campus',
            'location' => null,
            'summary' => 'A campus pledge that guides universities and colleges towards the long-term elimination of single-use disposable plastics.',
            'description' => '<p>Plastic Free Campus is part of the #BreakFreeFromPlastic movement: a pledge to guide campuses towards the long-term elimination of single-use disposable plastics.</p><h2>The PURE approach</h2><p>We support each campus across four areas:</p><ul><li><strong>Sustainability</strong>: understanding where single-use plastics are used.</li><li><strong>Strategy</strong>: planning practical alternatives.</li><li><strong>Communications</strong>: bringing students and staff with us.</li><li><strong>Change management</strong>: making the change last.</li></ul><h2>Evaluation and certification</h2><p>Campuses are evaluated against the pledge and can be certified by Think Ocean as they progress towards zero waste.</p>',
        ],
        [
            'slug' => 'fast-fashion-awareness',
            'title' => 'Fast Fashion Awareness',
            'short' => 'Rethinking fast fashion',
            'icon' => 'megaphone',
            'image' => 'deck-fashion-runway',
            'location' => 'London',
            'summary' => 'Raising awareness of the environmental impact of fast fashion, including a London Fashion Week collection made entirely from materials found on beaches.',
            'description' => '<p>The fashion industry is one of the world’s most polluting industries, and textile dyeing is a major cause of water pollution.</p><p>To show what is possible, Think Ocean created a London Fashion Week collection made entirely from materials found discarded on beaches. The collection was featured by Vogue.</p><p>We continue to use fashion to start conversations about how what we wear affects our oceans and waters.</p>',
        ],
        [
            'slug' => 'mini-statues-of-london',
            'title' => 'The Mini Statues of London',
            'short' => 'Mini Statues of London',
            'icon' => 'map',
            'image' => 'deck-mini-statues',
            'location' => 'London',
            'summary' => 'Transforming urban landscapes through art, storytelling and technology: a trail of miniature statues of endangered species across London.',
            'description' => '<p>The Mini Statues Collections turn cities into open-air galleries with engaging, educational art installations. Miniature statues of endangered species will be installed across key London locations, forming a connected trail through the city.</p><h2>What the project includes</h2><ul><li><strong>The statues</strong>: designed with renowned sculptor Steve Winterburn and emerging creatives from the Royal College of Art.</li><li><strong>An app</strong> that guides people to each sculpture and unlocks the story behind each species.</li><li><strong>Live or Let Die: The Brink of Extinction</strong>, a documentary series following each endangered species from its natural habitat to the heart of the city.</li><li><strong>MSOL Unplugged</strong>, a series of intimate live music performances celebrating music’s power to inspire change for the planet.</li></ul><p>The project is in active development. Sponsorship opportunities are available for individual statues. <a href="/contact?subject=Partnership">Get in touch</a> to find out more.</p>',
        ],
    ];

    /** Placeholder projects from the prototype, replaced by the real missions. */
    private const LEGACY_PROJECTS = [
        'coastal-community-action', 'reef-restoration-initiative', 'sustainable-fisheries-program', 'blue-carbon-project', 'ocean-education-programme',
    ];

    private User $user;

    /** @var array<string, int> image name => media id */
    private array $media = [];

    public function handle(ContentWorkflow $workflow, MediaLibrary $library, SiteSettings $settings): int
    {
        $user = User::query()->where('email', $this->option('user'))->first();

        if (! $user || ! $user->hasPermission(Permission::PublishContent) || ! $user->hasPermission(Permission::ManageNavigation)) {
            $this->components->error('Pass --user= with the email of an active administrator or editor.');

            return self::FAILURE;
        }

        $this->user = $user;

        // Resizing large photographs with GD needs more than the default 128 MB.
        ini_set('memory_limit', '1G');

        $this->components->task('Media library', fn () => $this->importMedia($library));
        $this->components->task('Pages', fn () => $this->importPages($workflow));
        $this->components->task('Projects', fn () => $this->importProjects($workflow));
        $this->components->task('News and insights', fn () => $this->importPosts($workflow));
        $this->components->task('Navigation', fn () => $this->importNavigation());

        if ($user->hasPermission(Permission::ManageSettings)) {
            $this->components->task('Site and Live TV settings', fn () => $this->importSettings($settings));
        } else {
            $this->components->warn('Skipped site settings: only an administrator can change them.');
        }

        $this->components->info($this->option('draft') ? 'Imported as drafts. Review and publish them in the CMS.' : 'Imported and published.');

        return self::SUCCESS;
    }

    private function importMedia(MediaLibrary $library): void
    {
        $credits = json_decode((string) @file_get_contents(public_path('images/think-ocean/CREDITS.json')), true) ?: [];

        $images = [
            'home-hero' => 'A turtle swimming among a school of fish',
            'about-hero' => 'Ocean waves on a bright day',
            'about-whale' => 'A humpback whale’s tail breaking the surface of the ocean',
            'about-quote' => 'Open ocean',
            'what-we-do-hero' => 'Ocean seen from above',
            'projects-hero' => 'Coastal waters',
            'get-involved-hero' => 'People by the sea',
            'get-involved-banner' => 'Waves rolling towards the shore',
            'news-hero' => 'Underwater scene',
            'contact-hero' => 'Calm sea at the coast',
            'focus-conservation' => 'A school of tropical fish swimming over a coral reef',
            'focus-solutions' => 'A fishing boat silhouetted against a sunset sea',
            'focus-climate' => 'A tropical coastline with palm trees and a sheltered bay',
            'focus-education' => 'Children writing in a classroom',
            'project-reef' => 'A coral reef',
            'project-fisheries' => 'A small fishing boat',
            'project-blue-carbon' => 'Mangrove roots in shallow water',
            'project-education' => 'Children learning outdoors',
            'news-turtle' => 'A sea turtle swimming',
            'news-cleanup' => 'Volunteers cleaning a beach',
            'news-fish' => 'Fish swimming over a reef',
            'news-boat' => 'A boat on the water',
            'news-mangrove' => 'A mangrove forest',
            'news-diver' => 'A diver underwater',
            'coast' => 'Waves rolling onto a sandy beach at sunset',
            'partner-impactoverse' => 'Impactoverse logo',
            'partner-upstream' => 'EU UPSTREAM logo',
            // From the Think Ocean presentation (NY session).
            'deck-cleanup' => 'The Think Ocean team with bags of litter collected at a community clean-up',
            'deck-rescue-centre' => 'Volunteers building the San Antonio Wildlife Rescue Centre in Chile',
            'deck-musa' => 'The Museo de San Antonio (MUSA) overlooking the coast in San Antonio, Chile',
            'deck-musa-whale' => 'A blue whale skeleton on display at the Museo de San Antonio',
            'deck-boat' => 'Think Ocean’s river boat moored on a canal',
            'deck-stop-at-source' => 'Diagram of the Stop at Source technology: the ramps, the bubble wall and the catchment device',
            'deck-bottle-display' => 'A “No to plastic bottles” display of reusable Think Ocean bottles',
            'deck-ntu-bottle' => 'A reusable bottle branded by Nottingham Trent University in partnership with Think Ocean',
            'deck-plastic-free-campus' => 'The #BreakFreeFromPlastic Campus Pledge badge',
            'deck-mini-statues' => 'Think Ocean presents The Mini Statues Collections',
            'deck-fashion-runway' => 'A model on the London Fashion Week runway wearing a design made from materials found on beaches',
            'deck-tv-logo' => 'Think Ocean Television logo',
            'deck-tv-banner' => 'Think Ocean TV, the Voice of the Planet: giving a bigger voice to those saving it',
            'deck-tv-live-or-let-die' => 'Live or Let Die: The Brink of Extinction',
            'deck-tv-planet-questions' => 'The Planet Questions with Sir Graham Brady',
            'deck-tv-birdlife' => 'BirdLife Cyprus and the Yellow-legged Gull',
            'deck-tv-unplugged' => 'MSOL Unplugged: an acoustic performance in front of a Think Ocean TV screen',
            'team-hugo-valdes-vera' => 'Hugo Valdes-Vera',
            'team-ana-alonso' => 'Ana Alonso',
            'team-martijn-pakker' => 'Martijn Pakker',
            'team-mercedes-alonso' => 'Mercedes Alonso',
            'team-dean-chetty' => 'Dean Chetty',
            'team-sal-sparace' => 'Sal Sparace',
            'partner-musa' => 'Museo de San Antonio (MUSA) logo',
            'partner-ntu' => 'Nottingham Trent University logo',
            'partner-aunua' => 'Aunua Global logo',
            'partner-derby' => 'University of Derby logo',
            'partner-horizon-europe' => 'European Commission Horizon Europe logo',
            'partner-unep' => 'UN Environment Programme logo',
        ];

        $files = [
            'coast' => public_path('images/think-ocean-coast.jpg'),
            'partner-impactoverse' => public_path('images/think-ocean/partners/impactoverse.png'),
            'partner-upstream' => public_path('images/think-ocean/partners/upstream.png'),
            'partner-musa' => public_path('images/think-ocean/partners/musa.jpg'),
            'partner-ntu' => public_path('images/think-ocean/partners/ntu.jpg'),
            'partner-aunua' => public_path('images/think-ocean/partners/aunua.jpg'),
            'partner-derby' => public_path('images/think-ocean/partners/derby.jpg'),
            'partner-horizon-europe' => public_path('images/think-ocean/partners/horizon-europe.jpg'),
            'partner-unep' => public_path('images/think-ocean/partners/unep.jpg'),
        ];

        foreach ($images as $name => $alt) {
            $source = $files[$name] ?? public_path('images/think-ocean/'.$name.'.jpg');
            $originalName = 'think-ocean-'.$name.'.'.pathinfo($source, PATHINFO_EXTENSION);

            if (! is_file($source)) {
                continue;
            }

            $existing = Media::query()->where('original_name', $originalName)->value('id');

            if ($existing) {
                $this->media[$name] = $existing;

                continue;
            }

            $path = 'media/think-ocean/'.Str::random(40).'.'.pathinfo($source, PATHINFO_EXTENSION);
            Storage::disk('local')->put($path, file_get_contents($source));

            $credit = $credits[$name]['by'] ?? null;

            $this->media[$name] = $library->register('local', $path, $originalName, [
                'title' => Str::headline($name),
                'alt_text' => $alt,
                'credit' => $credit ? 'Photo: '.$credit.' / Unsplash' : null,
            ], $this->user)->id;
        }
    }

    private function importPages(ContentWorkflow $workflow): void
    {
        $partners = [
            'type' => 'partners',
            'data' => [
                'heading' => 'Our Partners',
                'intro' => 'We work alongside organisations, communities and innovators who share our commitment to a healthier ocean.',
                'items' => [
                    ['name' => 'Horizon Europe (European Commission)', 'url' => 'https://commission.europa.eu', 'image_id' => $this->media['partner-horizon-europe'] ?? null],
                    ['name' => 'UN Environment Programme', 'url' => 'https://www.unep.org', 'image_id' => $this->media['partner-unep'] ?? null],
                    ['name' => 'University of Derby', 'url' => 'https://www.derby.ac.uk', 'image_id' => $this->media['partner-derby'] ?? null],
                    ['name' => 'Nottingham Trent University', 'url' => 'https://www.ntu.ac.uk', 'image_id' => $this->media['partner-ntu'] ?? null],
                    ['name' => 'Aunua Global', 'url' => null, 'image_id' => $this->media['partner-aunua'] ?? null],
                    ['name' => 'Museo de San Antonio-Chile (MUSA)', 'url' => null, 'image_id' => $this->media['partner-musa'] ?? null],
                    ['name' => 'Impactoverse', 'url' => 'https://impactoverse.com', 'image_id' => $this->media['partner-impactoverse'] ?? null],
                    ['name' => 'EU UPSTREAM', 'url' => 'https://upstream-project.eu', 'image_id' => $this->media['partner-upstream'] ?? null],
                    ['name' => 'World Genome Academy', 'url' => 'https://worldgeno.me', 'image_id' => null],
                    ['name' => 'Eight Partners Media', 'url' => null, 'image_id' => null],
                ],
                'link_label' => 'Become a partner',
                'link_url' => '/contact?subject=Partnership',
            ],
        ];

        $partnerUrl = '/contact?subject=Partnership';

        $pages = [
            'home' => [
                'title' => 'Home',
                'intro' => null,
                'sections' => [
                    ['type' => 'hero', 'data' => [
                        'heading' => 'Healthy Oceans. Shared Purpose. Collective Action.',
                        'text' => 'Empowering communities to protect the planet and restore our oceans and waters by 2030.',
                        'image_id' => $this->media['home-hero'] ?? null,
                        'primary_label' => 'Explore Our Projects', 'primary_url' => '/projects',
                        'secondary_label' => 'Partner With Us', 'secondary_url' => $partnerUrl,
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'The ocean matters. So does what we do next.',
                        'body' => '<p>The world doesn’t need more people just talking about environmental problems. It needs more people creating solutions.</p><p><strong>Think Ocean CIC is a community of doers.</strong> We turn ideas into action, partnerships into impact and innovation into measurable change, from stopping plastic before it reaches the ocean to inspiring the next generation.</p>',
                        'image_id' => $this->media['deck-cleanup'] ?? $this->media['coast'] ?? null,
                        'image_position' => 'right',
                        'link_label' => 'About Think Ocean', 'link_url' => '/about',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'Five missions. One healthier planet.',
                        'intro' => null,
                        'layout' => 'tiles',
                        'items' => array_map(fn (array $mission) => [
                            'title' => $mission['short'], 'icon' => $mission['icon'], 'link_url' => '/projects/'.$mission['slug'],
                        ], self::MISSIONS),
                    ]],
                    ['type' => 'featured_projects', 'data' => [
                        'heading' => 'Discover the work',
                        'intro' => 'Explore our missions and see how we’re making a difference.',
                        'style' => 'gallery',
                        'limit' => 3,
                    ]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Think Ocean TV: the Voice of the Planet',
                        'text' => 'An independent television channel giving a bigger voice to those protecting our planet. Launching through Amazon.',
                        'image_id' => $this->media['news-fish'] ?? null,
                        'primary_label' => 'Watch Think Ocean TV', 'primary_url' => '/watch',
                        'secondary_label' => 'Share your content', 'secondary_url' => 'mailto:'.self::TV_EMAIL,
                    ]],
                    $partners,
                ],
            ],
            'about' => [
                'title' => 'About Think Ocean',
                'intro' => 'Our story, our purpose and our commitment to a healthier ocean.',
                'meta_description' => 'Think Ocean CIC is a UK Community Interest Company and a community of doers, preventing plastic pollution, protecting biodiversity and empowering communities.',
                'sections' => [
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'An organisation focused on ocean-related change',
                        'body' => '<p>Think Ocean CIC is a UK Community Interest Company. We believe the world doesn’t need people just talking about environmental problems: it needs more people creating solutions.</p><p><strong>We are a community of doers.</strong> We turn ideas into action, partnerships into impact and innovation into measurable change. Whether it’s preventing plastic pollution before it reaches our oceans, protecting biodiversity, empowering communities, developing new technologies or inspiring the next generation, we focus on what we can do today to create a healthier planet tomorrow.</p>',
                        'image_id' => $this->media['deck-cleanup'] ?? $this->media['about-whale'] ?? null,
                        'image_position' => 'right',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => null,
                        'layout' => 'panel',
                        'items' => [
                            ['title' => 'Our Mission', 'text' => 'Empowering communities to protect the planet, and restoring our oceans and waters by 2030.', 'icon' => 'flag'],
                            ['title' => 'Our Vision', 'text' => 'A world where thriving oceans and rivers coexist with empowered, resilient communities, and where both nature and humanity flourish.', 'icon' => 'eye'],
                            ['title' => 'Our Principles', 'text' => 'Collaboration, innovation and action to eliminate plastic pollution and inspire lasting change.', 'icon' => 'shield-check'],
                        ],
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => 'Our story',
                        'heading' => 'Rooted in action since 1992',
                        'body' => '<p>Think Ocean’s roots lie with a group of environmentalists who, in 1992, united several organisations towards a common goal: to stop whale hunting in the South Pacific. Their work ended with the co-creation of the <strong>Southern Ocean Whale Sanctuary</strong> in 1995, a 50 million square kilometre area around Antarctica where commercial whaling is banned to protect whale populations.</p><p>The founding members continued working on environmental projects around the world. They created the San Antonio Wildlife Rescue Centre in Chile in response to the “El Niño” Pacific Ocean emergency, and supported organisations tackling whale hunting, deforestation and wildlife trafficking, as well as the adoption of the CITES treaty in Latin America.</p>',
                        'image_id' => $this->media['deck-rescue-centre'] ?? null,
                        'image_position' => 'left',
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'From a rescue centre in Chile to Think Ocean in the UK',
                        'body' => '<p>The work that began in San Antonio, Chile, grew from a small rescue centre into the Museo de San Antonio (MUSA), now one of our partners.</p><p>One of the founding members, <strong>Hugo Valdes-Vera</strong>, saw the impact of pollution, particularly plastic pollution travelling from rivers into the oceans. He went on to form Think Ocean in the UK to tackle the plastic pollution crisis, and was joined by <strong>Noleen Mariappen</strong>. Together they form the founding team of Think Ocean CIC.</p>',
                        'image_id' => $this->media['deck-musa'] ?? null,
                        'image_position' => 'right',
                    ]],
                    ['type' => 'quote', 'data' => [
                        'text' => 'Not talking. Doing.',
                        'attribution' => 'Hugo Valdes-Vera, Founder of Think Ocean',
                        'image_id' => $this->media['about-quote'] ?? null,
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => 'Our Values',
                        'intro' => 'What it means to be part of Think Ocean.',
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'We Do', 'text' => 'We turn ideas into action.', 'icon' => 'bolt'],
                            ['title' => 'Mission-Driven', 'text' => 'We are united by purpose.', 'icon' => 'flag'],
                            ['title' => 'Collaborative', 'text' => 'We achieve more together.', 'icon' => 'users'],
                            ['title' => 'Proactive', 'text' => 'We create opportunities and solve problems.', 'icon' => 'sparkles'],
                            ['title' => 'Reliable', 'text' => 'People can depend on us.', 'icon' => 'check-badge'],
                            ['title' => 'Curious & Innovative', 'text' => 'We never stop learning.', 'icon' => 'light-bulb'],
                            ['title' => 'Positive & Optimistic', 'text' => 'We believe change is possible.', 'icon' => 'star'],
                            ['title' => 'Adaptable', 'text' => 'We embrace change.', 'icon' => 'arrow-path'],
                            ['title' => 'Inclusive', 'text' => 'Everyone belongs.', 'icon' => 'globe'],
                            ['title' => 'Impact-Oriented', 'text' => 'We focus on meaningful outcomes.', 'icon' => 'arrow-trending-up'],
                        ],
                    ]],
                    ['type' => 'team', 'data' => [
                        'heading' => 'Meet the people behind Think Ocean',
                        'intro' => 'A collective effort. A shared commitment.',
                        'members' => [
                            ['name' => 'Hugo Valdes-Vera', 'role' => 'Founder', 'image_id' => $this->media['team-hugo-valdes-vera'] ?? null],
                            ['name' => 'Noleen Mariappen', 'role' => 'Co-founder', 'image_id' => null],
                            ['name' => 'Ana Alonso', 'role' => 'Digital transformation, innovation and partnerships', 'image_id' => $this->media['team-ana-alonso'] ?? null],
                            ['name' => 'Martijn Pakker', 'role' => 'Development strategist, MNPQ Consulting', 'image_id' => $this->media['team-martijn-pakker'] ?? null],
                            ['name' => 'Mercedes Alonso', 'role' => 'Sustainability and circular economy', 'image_id' => $this->media['team-mercedes-alonso'] ?? null],
                            ['name' => 'Dean Chetty', 'role' => 'Data strategy and architecture', 'image_id' => $this->media['team-dean-chetty'] ?? null],
                            ['name' => 'Sal Sparace', 'role' => 'Think Ocean TV, Eight Partners Media', 'image_id' => $this->media['team-sal-sparace'] ?? null],
                        ],
                        'link_label' => 'Join us', 'link_url' => '/get-involved',
                    ]],
                    $partners,
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Let’s work together',
                        'text' => 'Explore opportunities to collaborate with Think Ocean.',
                        'image_id' => $this->media['focus-conservation'] ?? null,
                        'primary_label' => 'Partner With Us', 'primary_url' => $partnerUrl,
                    ]],
                ],
            ],
            'what-we-do' => [
                'title' => 'What We Do',
                'intro' => 'Tackling ocean challenges through action, innovation and partnership.',
                'meta_title' => 'Our Work',
                'meta_description' => 'Think Ocean’s five missions: stopping river plastic at source, fighting single-use plastic bottles, plastic-free campuses, fast fashion awareness and the Mini Statues of London.',
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p><strong>Empowering communities. Protecting the planet.</strong> Our goal is to help restore our oceans and waters by 2030. Ocean challenges are interconnected, so our work brings together knowledge, people, technology and practical solutions across five missions.</p>',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => 'Our Five Missions',
                        'layout' => 'tiles',
                        'items' => array_map(fn (array $mission) => [
                            'title' => $mission['short'],
                            'image_id' => $this->media[$mission['image']] ?? null,
                            'link_url' => '/projects/'.$mission['slug'],
                            'link_label' => 'Learn more',
                        ], self::MISSIONS),
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => 'Mission 1',
                        'heading' => 'Stopping plastic before it reaches the ocean',
                        'body' => '<p>Our Stop at Source technology catches plastic in rivers using three components: <strong>the ramps</strong>, <strong>the bubble wall</strong> and <strong>the catchment device</strong>.</p><p>It is developed in partnership with the University of Derby, as part of the European Commission’s Horizon Europe mission to restore our ocean and waters by 2030.</p>',
                        'image_id' => $this->media['deck-stop-at-source'] ?? null,
                        'image_position' => 'left',
                        'link_label' => 'See the project', 'link_url' => '/projects/'.self::MISSIONS[0]['slug'],
                    ]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'From understanding to action.',
                        'text' => 'Find out more about our missions and how you can be part of them.',
                        'primary_label' => 'Explore Our Projects', 'primary_url' => '/projects',
                    ]],
                ],
            ],
            'projects' => [
                'title' => 'Our Projects',
                'intro' => 'Discover the initiatives working towards a healthier ocean.',
                'meta_description' => 'Think Ocean projects tackling ocean challenges, from marine conservation and sustainable livelihoods to climate resilience and education.',
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p>Every project is a step towards a cleaner, healthier ocean. Explore our projects and learn how you can get involved.</p>',
                    ]],
                ],
            ],
            'get-involved' => [
                'title' => 'Be Part of the Change',
                'intro' => 'A healthier ocean is a shared responsibility.',
                'meta_title' => 'Get Involved',
                'meta_description' => 'Support, volunteer, partner with or spread the word about Think Ocean.',
                'sections' => [
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => null,
                        'intro' => 'There are many ways to get involved with Think Ocean. Find the option that’s right for you.',
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'Support Our Work', 'icon' => 'heart', 'link_url' => '/donate'],
                            ['title' => 'Volunteer', 'icon' => 'hand-raised', 'link_url' => '#help'],
                            ['title' => 'Partner With Us', 'icon' => 'users', 'link_url' => $partnerUrl],
                            ['title' => 'Share Your Content', 'icon' => 'tv', 'link_url' => '/watch'],
                        ],
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => 'How you can be part of it',
                        'heading' => 'Content, volunteers and sponsors.',
                        'body' => '<p><strong>Share your content.</strong> Are you protecting the planet? Send your films, stories and projects to Think Ocean TV at <a href="mailto:'.self::TV_EMAIL.'">'.self::TV_EMAIL.'</a>.</p><p><strong>Volunteer.</strong> Join clean-ups, campaigns and events, and bring your skills to our missions.</p><p><strong>Sponsor.</strong> Support a mission, a Think Ocean TV programme or a statue in the Mini Statues of London.</p>',
                        'image_id' => $this->media['deck-cleanup'] ?? null,
                        'image_position' => 'right',
                    ]],
                    ['type' => 'enquiry_form', 'data' => [
                        'heading' => 'Tell us how you’d like to help',
                        'intro' => 'Your details will help us connect you with the right opportunities.',
                        'options' => "Volunteering\nPartnering with Think Ocean\nSponsorship\nSharing content with Think Ocean TV\nSupporting our work\nSomething else",
                        'image_id' => $this->media['news-turtle'] ?? null,
                    ]],
                ],
            ],
            'news' => [
                'title' => 'News & Insights',
                'intro' => 'Stories, ideas and updates.',
                'meta_description' => 'News, insights, stories and events from Think Ocean.',
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p>Explore the latest news, project updates and insights from Think Ocean.</p>',
                    ]],
                ],
            ],
            Page::TV_SLUG => [
                'title' => 'Think Ocean TV',
                'intro' => 'The Voice of the Planet. Giving a bigger voice to those saving it.',
                'meta_title' => 'Watch Think Ocean TV',
                'meta_description' => 'Think Ocean Television: an independent channel dedicated to the environment, sustainability, conservation, innovation and the people protecting our planet.',
                'og_image_id' => $this->media['deck-tv-banner'] ?? null,
                'sections' => [
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => 'Think Ocean Television',
                        'heading' => 'Giving a voice to those protecting our planet',
                        'body' => '<p><strong>We inform, inspire, promote solutions and empower people to become part of the change.</strong></p><p>Think Ocean Television is a fully independent channel dedicated exclusively to the environment, sustainability, conservation, innovation and the people working tirelessly to protect our planet.</p><p>Created by Think Ocean CIC alongside Eight Partners Media, it gives environmental organisations, scientists, innovators, educators, businesses, governments and local communities a global platform to share their work without political influence, commercial pressure or editorial bias. Launching through Amazon, it aims to reach audiences across many countries.</p><p>Our commitment is simple: <strong>to tell the truth, promote solutions and inspire action.</strong></p>',
                        'image_id' => $this->media['deck-tv-banner'] ?? null,
                        'image_position' => 'right',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => 'Think Ocean TV Originals',
                        'intro' => 'Original programmes made with and for the people protecting our planet.',
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'The Planet Questions', 'text' => 'Conversations about the future of our planet, with Sir Graham Brady.', 'image_id' => $this->media['deck-tv-planet-questions'] ?? null],
                            ['title' => 'Live or Let Die', 'text' => 'The brink of extinction: a documentary series following endangered species, one at a time.', 'image_id' => $this->media['deck-tv-live-or-let-die'] ?? null],
                            ['title' => 'BirdLife Cyprus & the Yellow-legged Gull', 'text' => 'Conservation stories from the people on the ground.', 'image_id' => $this->media['deck-tv-birdlife'] ?? null],
                            ['title' => 'MSOL Unplugged', 'text' => 'Intimate live music performances inspiring change for the planet.', 'image_id' => $this->media['deck-tv-unplugged'] ?? null],
                        ],
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => 'What you’ll watch',
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'Documentaries', 'text' => 'Environmental documentaries and ocean and wildlife conservation.', 'icon' => 'camera'],
                            ['title' => 'News', 'text' => 'Climate and sustainability news and scientific discoveries.', 'icon' => 'globe'],
                            ['title' => 'Solutions', 'text' => 'Innovative technologies, renewable energy and circular economy initiatives.', 'icon' => 'light-bulb'],
                            ['title' => 'Interviews', 'text' => 'Global experts, purposeful leaders and business sustainability case studies.', 'icon' => 'users'],
                            ['title' => 'Communities', 'text' => 'Community and NGO projects from around the world.', 'icon' => 'heart'],
                            ['title' => 'Education', 'text' => 'Educational, youth and education programming.', 'icon' => 'academic-cap'],
                            ['title' => 'Live events', 'text' => 'Live conferences and international events.', 'icon' => 'tv'],
                            ['title' => 'Independent', 'text' => 'Truthful. Global. Dedicated to our planet.', 'icon' => 'shield-check'],
                        ],
                    ]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Share your content',
                        'text' => 'Are you protecting the planet? Send us your films, stories and projects, or ask about sponsoring a programme.',
                        'primary_label' => 'Email '.self::TV_EMAIL, 'primary_url' => 'mailto:'.self::TV_EMAIL.'?subject=Think%20Ocean%20TV%20content',
                        'secondary_label' => 'Become a sponsor', 'secondary_url' => $partnerUrl,
                    ]],
                ],
            ],
            'contact' => [
                'title' => 'Contact Think Ocean',
                'intro' => 'We’d love to hear from you.',
                'meta_title' => 'Contact',
                'meta_description' => 'Contact Think Ocean about a question, partnership idea or way to support healthier oceans.',
                'sections' => [],
            ],
            'donate' => [
                'title' => 'Support Our Work',
                'intro' => 'Help us create lasting change for the ocean.',
                'meta_title' => 'Donate',
                'meta_description' => 'Support Think Ocean’s work for healthier oceans and more resilient coastal communities.',
                'sections' => [
                    ['type' => 'donation_form', 'data' => [
                        'heading' => 'Make a donation',
                        'intro' => 'Your gift funds practical conservation, education and community action for a healthier ocean.',
                        'amounts' => '10, 25, 50, 100',
                        'allow_monthly' => true,
                        'items_heading' => 'Your support can enable',
                        'items' => [
                            ['title' => 'Community action', 'icon' => 'users'],
                            ['title' => 'Ocean education', 'icon' => 'book-open'],
                            ['title' => 'Research collaboration', 'icon' => 'beaker'],
                            ['title' => 'Habitat protection', 'icon' => 'heart'],
                            ['title' => 'Climate-resilience initiatives', 'icon' => 'sun'],
                        ],
                    ]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Prefer to talk first?',
                        'text' => 'Speak with the Think Ocean team about other ways to give, including in-kind support.',
                        'primary_label' => 'Email us', 'primary_url' => 'mailto:'.self::EMAIL.'?subject=Think%20Ocean%20Support%20Enquiry',
                    ]],
                ],
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'intro' => null,
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p>Think Ocean uses personal information only when you contact us, subscribe to updates or ask to participate in our activities. We use it to answer your enquiry, send the updates you asked for, coordinate agreed activities and meet legal obligations.</p><h2>Information we receive</h2><p>This may include your name, email address and information you choose to include in a message.</p><h2>Your choices</h2><p>You may request access, correction or deletion, or unsubscribe from updates, by emailing <a href="mailto:'.self::EMAIL.'">'.self::EMAIL.'</a>.</p>',
                    ]],
                ],
            ],
            'accessibility' => [
                'title' => 'Accessibility',
                'intro' => null,
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p>Think Ocean aims to provide a website that can be used by as many people as possible, including keyboard and assistive-technology users.</p><h2>Current measures</h2><p>The website uses semantic headings, visible keyboard focus, descriptive image text, responsive layouts and reduced-motion support.</p><h2>Report a problem</h2><p>If you experience an accessibility barrier, email <a href="mailto:'.self::EMAIL.'">'.self::EMAIL.'</a> and describe the page and problem.</p>',
                    ]],
                ],
            ],
        ];

        foreach ($pages as $slug => $content) {
            $content = ['slug' => $slug, 'meta_title' => null, 'meta_description' => null, 'og_image_id' => null, ...$content];
            $page = Page::query()->where('slug', $slug)->first();

            $page = $page
                ? $workflow->saveDraft($page, $content, $this->user)
                : $workflow->create(Page::class, $content, $this->user);

            $this->publish($workflow, $page);
        }
    }

    private function importProjects(ContentWorkflow $workflow): void
    {
        // The prototype's sample projects are moved to the bin (restorable) in favour of the real missions.
        Project::query()->whereIn('slug', self::LEGACY_PROJECTS)->get()->each->delete();

        foreach (self::MISSIONS as $mission) {
            if (Project::withTrashed()->where('slug', $mission['slug'])->exists()) {
                continue;
            }

            $project = $workflow->create(Project::class, [
                'title' => $mission['title'],
                'slug' => $mission['slug'],
                'summary' => $mission['summary'],
                'description' => $mission['description'],
                'featured_image_id' => $this->media[$mission['image']] ?? null,
                'location' => $mission['location'],
            ], $this->user);

            $this->publish($workflow, $project);
        }
    }

    private function importPosts(ContentWorkflow $workflow): void
    {
        $categories = collect(['News', 'Insights', 'Stories', 'Events'])->mapWithKeys(fn (string $name) => [
            $name => Category::query()->firstOrCreate(
                ['type' => Category::TYPE_POST, 'slug' => Str::slug($name)],
                ['name' => $name],
            )->id,
        ]);

        $posts = [
            ['Why ocean health matters to everyone', 'News', 'Healthy oceans support climate resilience, livelihoods, biodiversity and community wellbeing.', null, '2026-10-13 10:04:00'],
            ['New partnership to restore coastal habitats', 'News', 'How working with local partners helps bring damaged coastal habitats back to health.', 'news-turtle', '2026-10-08 11:39:36'],
            ['How blue carbon helps fight climate change', 'Insights', 'Mangroves, seagrass and salt marshes store carbon and protect coastlines. Here is why they matter.', 'news-cleanup', '2026-10-09 11:39:36'],
            ['A day in the life of a marine biologist', 'Stories', 'From early-morning dives to data analysis: what ocean science looks like up close.', 'news-fish', '2026-10-10 11:39:36'],
            ['World Ocean Day – Join us!', 'Events', 'Celebrate World Ocean Day with community activities that anyone can take part in.', 'news-boat', '2026-10-11 11:39:36'],
            ['Community-led mangrove restoration begins', 'News', 'Local communities are leading the way in restoring mangrove forests along the coast.', 'news-mangrove', '2026-10-12 11:39:36'],
            ['What you can do to reduce ocean plastic', 'Insights', 'Simple everyday changes that add up to less plastic reaching the ocean.', 'news-diver', '2026-10-13 11:39:36'],
        ];

        foreach ($posts as [$title, $category, $excerpt, $image, $date]) {
            $slug = Str::slug($title);

            if (Post::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $post = $workflow->create(Post::class, [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'body' => '<p>'.e($excerpt).'</p>',
                'featured_image_id' => $image ? ($this->media[$image] ?? null) : null,
                'published_at' => $date,
                'category_ids' => [$categories[$category]],
            ], $this->user);

            $this->publish($workflow, $post);
        }
    }

    private function importNavigation(): void
    {
        $pageId = fn (string $slug) => Page::query()->where('slug', $slug)->value('id');

        $menu = [
            ['About', 'page', 'about'],
            ['Our Work', 'page', 'what-we-do'],
            ['Projects', 'url', '/projects'],
            ['Watch', 'url', '/watch'],
            ['News & Insights', 'url', '/news'],
            ['Get Involved', 'page', 'get-involved'],
            ['Contact', 'url', '/contact'],
        ];

        $menus = [
            'header' => $menu,
            'footer' => [['Home', 'url', '/'], ...$menu, ['Donate', 'page', 'donate']],
        ];

        foreach ($menus as $location => $items) {
            NavigationItem::query()->where('location', $location)->delete();

            foreach ($items as $order => [$label, $type, $target]) {
                $item = new NavigationItem;
                $item->forceFill([
                    'location' => $location,
                    'label' => $label,
                    'link_type' => $type,
                    'page_id' => $type === 'page' ? $pageId($target) : null,
                    'url' => $type === 'url' ? $target : null,
                    'open_in_new_tab' => false,
                    'is_active' => true,
                    'sort_order' => $order,
                ])->save();
            }
        }
    }

    private function importSettings(SiteSettings $settings): void
    {
        $settings->set([
            'tagline' => 'Empowering communities. Protecting the planet.',
            'default_meta_title' => 'Think Ocean | Healthy Oceans. Shared Purpose. Collective Action.',
            'default_meta_description' => 'Think Ocean CIC is a community of doers preventing plastic pollution, protecting biodiversity and empowering communities to restore our oceans and waters by 2030.',
            'default_og_image_id' => $this->media['home-hero'] ?? null,
            'contact_email' => self::EMAIL,
            'tv_email' => $settings->get('tv_email') ?: self::TV_EMAIL,
            'contact_notification_email' => $settings->get('contact_notification_email') ?: self::EMAIL,
        ]);

        // Live TV is only set up the first time, so a re-run never overwrites what staff have changed.
        if (blank($settings->get('live_stream_url'))) {
            $settings->set([
                'live_enabled' => true,
                'live_title' => 'THINK OCEAN TV - AMAZON FIRE TV Stream',
                'live_description' => 'Watch ocean stories, conversations and live coverage of our projects and events.',
                'live_stream_url' => 'https://rst5.saiuzwebnetwork.it:3635/hybrid/play.m3u8',
                'live_schedule' => null,
            ]);
        }

        // Replace the original placeholder description, but never one staff have written.
        if (in_array($settings->get('live_description'), [null, '', 'Watch ocean stories, conversations and live coverage of our projects and events.'], true)) {
            $settings->set(['live_description' => 'Think Ocean TV, the Voice of the Planet: an independent channel for the environment, sustainability, conservation and innovation.']);
        }

        if (blank($settings->get('watch_videos'))) {
            $settings->set(['watch_videos' => [
                ['title' => 'Think Ocean Television: giving a voice to those protecting our planet', 'url' => 'https://youtu.be/BYx2GnHXrgo', 'image_id' => $this->media['deck-tv-logo'] ?? null],
            ]]);
        }
    }

    private function publish(ContentWorkflow $workflow, Model $model): void
    {
        $alreadyLive = $model->is_published && ! $model->hasPendingDraft();

        if (! $this->option('draft') && ! $alreadyLive) {
            $workflow->publish($model, $this->user, 'Imported from the original Think Ocean website.');
        }
    }
}
