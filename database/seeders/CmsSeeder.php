<?php

namespace Database\Seeders;

use App\Enums\ReviewStatus;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates the core page structure and menus.
 *
 * Pages are created as unpublished drafts. Text that must come from the
 * organisation is marked "[PLACEHOLDER: …]", and the publishing workflow
 * refuses to publish content that still contains a placeholder. Nothing
 * here invents facts, figures, people or contact details.
 *
 * Safe to run more than once: existing pages and menu items are left untouched.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => $page) {
            if (Page::withTrashed()->where('slug', $slug)->exists()) {
                continue;
            }

            (new Page)->forceFill([
                'title' => $page['title'],
                'slug' => $slug,
                'is_system' => true,
                'intro' => $page['intro'] ?? null,
                'sections' => $page['sections'] ?? [],
                'review_status' => ReviewStatus::Draft,
                'is_published' => false,
            ])->save();
        }

        if (NavigationItem::query()->doesntExist()) {
            $this->seedNavigation();
        }
    }

    private function seedNavigation(): void
    {
        $pageId = fn (string $slug) => Page::query()->where('slug', $slug)->value('id');

        $menus = [
            'header' => [
                ['About', 'page', 'about'],
                ['What We Do', 'page', 'what-we-do'],
                ['Projects', 'url', '/projects'],
                ['News & Insights', 'url', '/news'],
                ['Get Involved', 'page', 'get-involved'],
            ],
            'footer' => [
                ['About', 'page', 'about'],
                ['What We Do', 'page', 'what-we-do'],
                ['Projects', 'url', '/projects'],
                ['News & Insights', 'url', '/news'],
                ['Get Involved', 'page', 'get-involved'],
                ['Contact', 'url', '/contact'],
            ],
            'legal' => [
                ['Privacy policy', 'page', 'privacy-policy'],
                ['Cookie policy', 'page', 'cookie-policy'],
                ['Accessibility', 'page', 'accessibility'],
            ],
        ];

        foreach ($menus as $location => $items) {
            foreach ($items as $order => [$label, $type, $target]) {
                NavigationItem::query()->create([
                    'location' => $location,
                    'label' => $label,
                    'link_type' => $type,
                    'page_id' => $type === 'page' ? $pageId($target) : null,
                    'url' => $type === 'url' ? $target : null,
                    'sort_order' => $order,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function pages(): array
    {
        $placeholderItems = fn (int $count, string $what) => array_map(fn (int $i) => [
            'title' => "[PLACEHOLDER: {$what} {$i}]",
            'text' => "[PLACEHOLDER: one or two sentences describing a confirmed {$what}.]",
            'icon' => null,
        ], range(1, $count));

        return [
            'home' => [
                'title' => 'Home',
                'sections' => [
                    ['type' => 'hero', 'data' => [
                        'heading' => 'Healthy Oceans. Thriving Futures.',
                        'text' => '[PLACEHOLDER: one or two sentences introducing Think Ocean CIC.]',
                        'primary_label' => 'Explore Our Work',
                        'primary_url' => '/what-we-do',
                        'secondary_label' => 'Get Involved',
                        'secondary_url' => '/get-involved',
                    ]],
                    ['type' => 'rich_text', 'data' => [
                        'eyebrow' => 'About Think Ocean',
                        'heading' => '[PLACEHOLDER: introduction heading]',
                        'body' => '<p>[PLACEHOLDER: a concise introduction to Think Ocean CIC and a clear explanation of its purpose.]</p>',
                    ]],
                    ['type' => 'feature_grid', 'data' => [
                        'eyebrow' => 'What we do',
                        'heading' => '[PLACEHOLDER: heading for the organisation\'s activities]',
                        'intro' => null,
                        'items' => $placeholderItems(3, 'activity'),
                    ]],
                    ['type' => 'featured_projects', 'data' => ['eyebrow' => 'Our work', 'heading' => 'Featured projects', 'limit' => 3]],
                    ['type' => 'latest_news', 'data' => ['eyebrow' => 'Latest', 'heading' => 'News and insights', 'limit' => 3]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Get involved',
                        'text' => '[PLACEHOLDER: describe how people can support Think Ocean. Only include activities the organisation has confirmed it supports.]',
                        'primary_label' => 'Ways to get involved',
                        'primary_url' => '/get-involved',
                        'secondary_label' => 'Contact us',
                        'secondary_url' => '/contact',
                    ]],
                ],
            ],
            'about' => [
                'title' => 'About Think Ocean',
                'intro' => '[PLACEHOLDER: short introduction to the organisation.]',
                'sections' => [
                    ['type' => 'rich_text', 'data' => [
                        'heading' => 'Our purpose',
                        'body' => '<p>[PLACEHOLDER: the organisation\'s purpose, story and values, as approved by the directors.]</p>',
                    ]],
                ],
            ],
            'what-we-do' => [
                'title' => 'What We Do',
                'intro' => '[PLACEHOLDER: short summary of the organisation\'s work.]',
                'sections' => [
                    ['type' => 'feature_grid', 'data' => ['heading' => null, 'items' => $placeholderItems(3, 'activity')]],
                ],
            ],
            'get-involved' => [
                'title' => 'Get Involved',
                'intro' => '[PLACEHOLDER: short introduction. Only list ways of getting involved that the organisation has confirmed it supports.]',
                'sections' => [
                    ['type' => 'feature_grid', 'data' => ['heading' => null, 'items' => $placeholderItems(2, 'way to get involved')]],
                    ['type' => 'call_to_action', 'data' => [
                        'heading' => 'Talk to us',
                        'text' => 'Send us a message and we will get back to you.',
                        'primary_label' => 'Contact us',
                        'primary_url' => '/contact',
                    ]],
                ],
            ],
            'contact' => [
                'title' => 'Contact us',
                'intro' => 'We would be glad to hear from you. Send us a message using the form below.',
            ],
            'projects' => [
                'title' => 'Projects',
                'intro' => '[PLACEHOLDER: short introduction to Think Ocean\'s projects.]',
            ],
            'news' => [
                'title' => 'News and insights',
                'intro' => '[PLACEHOLDER: short introduction to news and insights.]',
            ],
            'privacy-policy' => [
                'title' => 'Privacy policy',
                'sections' => [['type' => 'rich_text', 'data' => ['body' => implode('', [
                    '<p>[PLACEHOLDER: The privacy policy must be written or reviewed by the organisation, with legal advice where needed, before it is published.]</p>',
                    '<p>For reference, this website itself processes the following personal data:</p>',
                    '<ul><li>Contact form: name, email address, optional organisation and subject, the message, the date and time, and the sender\'s IP address and browser details (used to prevent abuse).</li>',
                    '<li>Staff accounts for the content management system.</li>',
                    '<li>Essential cookies described in the cookie policy.</li></ul>',
                    '<p>[PLACEHOLDER: who the data controller is and how to contact them; the lawful basis for each use; how long contact messages are kept; who data is shared with (for example the hosting provider); and people\'s rights under UK GDPR, including the right to complain to the Information Commissioner\'s Office.]</p>',
                ])]]],
            ],
            'cookie-policy' => [
                'title' => 'Cookie policy',
                'sections' => [['type' => 'rich_text', 'data' => ['body' => implode('', [
                    '<p>[PLACEHOLDER: Review and confirm this policy before publishing, including whether any analytics or embedded third-party content has been added.]</p>',
                    '<p>As built, this website sets only cookies that are strictly necessary for it to work:</p>',
                    '<ul><li><strong>Session cookie</strong> — keeps the contact form and staff sign-in working securely. Deleted when you close your browser or after a period of inactivity.</li>',
                    '<li><strong>XSRF-TOKEN</strong> — protects forms against cross-site request forgery.</li></ul>',
                    '<p>The website does not use advertising or tracking cookies.</p>',
                ])]]],
            ],
            'accessibility' => [
                'title' => 'Accessibility statement',
                'sections' => [['type' => 'rich_text', 'data' => ['body' => implode('', [
                    '<p>[PLACEHOLDER: Complete this statement after the website has been tested. Include the date of the last review, any known issues and how to report a problem.]</p>',
                    '<p>This website has been designed to follow the Web Content Accessibility Guidelines (WCAG) 2.2 at level AA. It aims to support keyboard navigation, screen readers, text resizing and zoom, and the reduced-motion setting.</p>',
                    '<p>[PLACEHOLDER: how to contact the organisation about accessibility, and the expected response time.]</p>',
                ])]]],
            ],
        ];
    }
}
