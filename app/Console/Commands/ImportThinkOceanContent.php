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

    private const EMAIL = 'thinkocean@gmail.com';

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
        ];

        $files = [
            'coast' => public_path('images/think-ocean-coast.jpg'),
            'partner-impactoverse' => public_path('images/think-ocean/partners/impactoverse.png'),
            'partner-upstream' => public_path('images/think-ocean/partners/upstream.png'),
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
                    ['name' => 'European Commission', 'url' => 'https://commission.europa.eu', 'image_id' => null],
                    ['name' => 'World Genome Academy', 'url' => 'https://worldgeno.me', 'image_id' => null],
                    ['name' => 'Six Partners Media', 'url' => null, 'image_id' => null],
                    ['name' => 'Annua Global', 'url' => null, 'image_id' => null],
                    ['name' => 'Museo de San Antonio-Chile (MUSA)', 'url' => null, 'image_id' => null],
                    ['name' => 'Impactoverse', 'url' => 'https://impactoverse.com', 'image_id' => $this->media['partner-impactoverse'] ?? null],
                    ['name' => 'EU UPSTREAM', 'url' => 'https://upstream-project.eu', 'image_id' => $this->media['partner-upstream'] ?? null],
                    ['name' => 'University of Derby', 'url' => 'https://www.derby.ac.uk', 'image_id' => null],
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
                        'text' => 'Together, we can protect our ocean and build a more sustainable future.',
                        'image_id' => $this->media['home-hero'] ?? null,
                        'primary_label' => 'Explore Our Projects', 'primary_url' => '/projects',
                        'secondary_label' => 'Partner With Us', 'secondary_url' => $partnerUrl,
                    ]],
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'The ocean matters. So does what we do next.',
                        'body' => '<p>Think Ocean CIC brings people, ideas and action together to support a healthier ocean.</p>',
                        'image_id' => $this->media['coast'] ?? null,
                        'image_position' => 'right',
                        'link_label' => 'About Think Ocean', 'link_url' => '/about',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'Exploring the challenges. Connecting the solutions.',
                        'intro' => null,
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'Ocean Conservation', 'icon' => 'globe', 'link_url' => '/what-we-do'],
                            ['title' => 'Research & Innovation', 'icon' => 'beaker', 'link_url' => '/what-we-do'],
                            ['title' => 'Education & Awareness', 'icon' => 'academic-cap', 'link_url' => '/what-we-do'],
                            ['title' => 'Collaboration & Partnerships', 'icon' => 'users', 'link_url' => '/what-we-do'],
                        ],
                    ]],
                    ['type' => 'featured_projects', 'data' => [
                        'heading' => 'Discover the work',
                        'intro' => 'Explore our projects and see how we’re making a difference.',
                        'style' => 'gallery',
                        'limit' => 3,
                    ]],
                    $partners,
                ],
            ],
            'about' => [
                'title' => 'About Think Ocean',
                'intro' => 'Our story, our purpose and our commitment to a healthier ocean.',
                'meta_description' => 'Think Ocean is a UK-based Community Interest Company protecting marine ecosystems and building more resilient coastal communities.',
                'sections' => [
                    ['type' => 'image_text', 'data' => [
                        'eyebrow' => null,
                        'heading' => 'An organisation focused on ocean-related change',
                        'body' => '<p>Think Ocean CIC is a Community Interest Company dedicated to creating positive change for our ocean through collaboration, innovation and action.</p><p>We work with individuals, organisations and communities to address ocean challenges and support a more sustainable future.</p>',
                        'image_id' => $this->media['about-whale'] ?? null,
                        'image_position' => 'right',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => null,
                        'layout' => 'panel',
                        'items' => [
                            ['title' => 'Our Mission', 'text' => 'To connect people, knowledge and action for a healthier ocean.', 'icon' => 'sparkles'],
                            ['title' => 'Our Vision', 'text' => 'A thriving ocean for future generations.', 'icon' => 'eye'],
                            ['title' => 'Our Values', 'text' => 'Integrity, Collaboration, Innovation, Impact.', 'icon' => 'shield-check'],
                        ],
                    ]],
                    ['type' => 'team', 'data' => [
                        'heading' => 'Meet the people behind Think Ocean',
                        'intro' => 'A collective effort. A shared commitment.',
                        'members' => [],
                        'link_label' => null, 'link_url' => null,
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
                'meta_description' => 'Think Ocean’s focus areas: marine conservation, research and innovation, education and partnerships.',
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => null,
                        'body' => '<p>Ocean challenges are interconnected. Our work brings together knowledge, people and practical solutions.</p>',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'heading' => 'Our Focus Areas',
                        'layout' => 'tiles',
                        'items' => [
                            ['title' => 'Marine Conservation', 'image_id' => $this->media['focus-conservation'] ?? null],
                            ['title' => 'Research & Innovation', 'image_id' => $this->media['focus-solutions'] ?? null],
                            ['title' => 'Education & Awareness', 'image_id' => $this->media['focus-education'] ?? null],
                            ['title' => 'Collaboration & Partnerships', 'image_id' => $this->media['focus-climate'] ?? null],
                        ],
                    ]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'From understanding to action.',
                        'text' => 'Find out more about our approach.',
                        'primary_label' => 'Explore Our Work', 'primary_url' => '/projects',
                    ]],
                    ['type' => 'image', 'data' => ['image_id' => $this->media['coast'] ?? null]],
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
                            ['title' => 'Spread the Word', 'icon' => 'megaphone', 'link_url' => '/news'],
                        ],
                    ]],
                    ['type' => 'enquiry_form', 'data' => [
                        'heading' => 'Tell us how you’d like to help',
                        'intro' => 'Your details will help us connect you with the right opportunities.',
                        'options' => "Volunteering\nPartnering with Think Ocean\nSupporting our work\nSomething else",
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
        $projects = [
            ['Coastal community action', 'Practical local action that protects coastlines, improves awareness and brings people together around ocean health.', null, 'United Kingdom and partner communities'],
            ['Reef Restoration Initiative', 'Supporting coral reef recovery and biodiversity in coastal regions.', 'project-reef', null],
            ['Sustainable Fisheries Program', 'Promoting responsible fishing and stronger coastal economies.', 'project-fisheries', null],
            ['Blue Carbon Project', 'Restoring mangroves and seagrass for climate and community resilience.', 'project-blue-carbon', null],
            ['Ocean Education Programme', 'Inspiring the next generation of ocean stewards.', 'project-education', null],
        ];

        foreach ($projects as [$title, $summary, $image, $location]) {
            $slug = Str::slug($title);

            if (Project::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            $project = $workflow->create(Project::class, [
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary,
                'description' => '<p>'.e($summary).'</p>',
                'featured_image_id' => $image ? ($this->media[$image] ?? null) : null,
                'location' => $location,
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
            'tagline' => 'A healthier ocean. A better future.',
            'default_meta_title' => 'Think Ocean | Healthy Oceans. Shared Purpose. Collective Action.',
            'default_meta_description' => 'Think Ocean CIC brings people, ideas and action together to support a healthier ocean.',
            'default_og_image_id' => $this->media['home-hero'] ?? null,
            'contact_email' => $settings->get('contact_email') ?: self::EMAIL,
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
    }

    private function publish(ContentWorkflow $workflow, Model $model): void
    {
        $alreadyLive = $model->is_published && ! $model->hasPendingDraft();

        if (! $this->option('draft') && ! $alreadyLive) {
            $workflow->publish($model, $this->user, 'Imported from the original Think Ocean website.');
        }
    }
}
