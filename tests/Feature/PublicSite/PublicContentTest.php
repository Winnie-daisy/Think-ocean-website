<?php

namespace Tests\Feature\PublicSite;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Services\ContentWorkflow;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_works_before_any_content_is_published(): void
    {
        $this->seed(CmsSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Healthy Oceans.')
            ->assertSee('Our projects will be shared here soon')
            ->assertDontSee('[PLACEHOLDER', false);
    }

    public function test_the_published_homepage_renders_its_sections(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'sections' => [
                ['type' => 'hero', 'data' => ['heading' => 'Healthy Oceans. Thriving Futures.', 'text' => 'Approved intro copy', 'primary_label' => 'Explore Our Work', 'primary_url' => '/what-we-do']],
                ['type' => 'featured_projects', 'data' => ['heading' => 'Featured projects']],
            ],
        ]);
        Project::factory()->published()->create(['title' => 'Live Project', 'is_featured' => true]);
        Project::factory()->create(['title' => 'Draft Project', 'is_featured' => true]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Approved intro copy')
            ->assertSee('Live Project')
            ->assertDontSee('Draft Project');
    }

    public function test_draft_pages_are_not_publicly_accessible(): void
    {
        Page::factory()->create(['slug' => 'secret-plans', 'title' => 'Secret Plans']);
        Page::factory()->create(['slug' => 'about', 'title' => 'About Draft']);

        $this->get('/secret-plans')->assertNotFound();
        $this->get('/about')->assertNotFound();
    }

    public function test_unpublished_projects_are_hidden_from_listings_and_detail_pages(): void
    {
        Project::factory()->published()->create(['title' => 'Published Reef Work', 'slug' => 'published-reef-work']);
        Project::factory()->create(['title' => 'Unpublished Kelp Work', 'slug' => 'unpublished-kelp-work']);
        Project::factory()->published(now()->addWeek())->create(['title' => 'Scheduled Seal Work', 'slug' => 'scheduled-seal-work']);

        $this->get('/projects')
            ->assertOk()
            ->assertSee('Published Reef Work')
            ->assertDontSee('Unpublished Kelp Work')
            ->assertDontSee('Scheduled Seal Work');

        $this->get('/projects/published-reef-work')->assertOk();
        $this->get('/projects/unpublished-kelp-work')->assertNotFound();
        $this->get('/projects/scheduled-seal-work')->assertNotFound();
    }

    public function test_unpublished_news_is_hidden_from_listings_and_detail_pages(): void
    {
        Post::factory()->published()->create(['title' => 'Published Story', 'slug' => 'published-story']);
        Post::factory()->create(['title' => 'Draft Story', 'slug' => 'draft-story']);
        $trashed = Post::factory()->published()->create(['title' => 'Deleted Story', 'slug' => 'deleted-story']);
        app(ContentWorkflow::class)->delete($trashed, $this->editor());

        $this->get('/news')
            ->assertOk()
            ->assertSee('Published Story')
            ->assertDontSee('Draft Story')
            ->assertDontSee('Deleted Story');

        $this->get('/news/draft-story')->assertNotFound();
        $this->get('/news/deleted-story')->assertNotFound();
    }

    public function test_pending_draft_changes_never_appear_on_listings(): void
    {
        $editor = $this->editor();
        $post = Post::factory()->published()->create(['title' => 'Approved Title', 'slug' => 'approved-title']);
        app(ContentWorkflow::class)->saveDraft($post->fresh(), ['title' => 'Unapproved Title'], $editor);

        $this->get('/news')->assertSee('Approved Title')->assertDontSee('Unapproved Title');
        $this->get('/news/approved-title')->assertSee('Approved Title')->assertDontSee('Unapproved Title');
    }

    public function test_search_returns_only_published_content(): void
    {
        Project::factory()->published()->create(['title' => 'Mangrove Restoration']);
        Project::factory()->create(['title' => 'Mangrove Secret Draft']);
        Post::factory()->published()->create(['title' => 'Mangrove News']);
        $live = Post::factory()->published()->create(['title' => 'Coral Update', 'slug' => 'coral-update']);
        app(ContentWorkflow::class)->saveDraft($live->fresh(), ['title' => 'Mangrove Pending Rename'], $this->editor());

        $this->get('/search?q=mangrove')
            ->assertOk()
            ->assertSee('Mangrove Restoration')
            ->assertSee('Mangrove News')
            ->assertDontSee('Mangrove Secret Draft')
            ->assertDontSee('Mangrove Pending Rename');
    }

    public function test_search_handles_wildcards_and_short_queries(): void
    {
        Project::factory()->published()->create(['title' => 'Kelp']);

        $this->get('/search?q=%25')->assertOk()->assertSee('Please enter at least two characters');
        $this->get('/search?q=%25%25')->assertOk()->assertDontSee('Kelp');
    }

    public function test_missing_images_and_empty_content_are_handled_gracefully(): void
    {
        Storage::fake('public');
        $media = new Media;
        $media->forceFill(['disk' => 'public', 'visibility' => 'public', 'path' => 'media/gone.jpg', 'original_name' => 'gone.jpg', 'mime_type' => 'image/jpeg'])->save();

        $project = Project::factory()->published()->create(['slug' => 'imageless', 'featured_image_id' => $media->id, 'gallery' => [$media->id, 99999], 'description' => null, 'summary' => null]);
        $media->delete();

        Page::factory()->published()->create(['slug' => 'odd-page', 'sections' => [
            ['type' => 'unknown_block', 'data' => []],
            ['type' => 'hero', 'data' => ['heading' => 'Still renders', 'image_id' => 424242]],
            'not-even-an-array',
            ['type' => 'feature_grid', 'data' => ['items' => []]],
        ]]);

        $this->get('/projects/imageless')->assertOk();
        $this->get('/odd-page')->assertOk()->assertSee('Still renders');
        $this->get('/projects')->assertOk();
        $this->get('/news')->assertOk()->assertSee('No articles published yet');
    }

    public function test_rich_text_is_sanitised_on_output(): void
    {
        Post::factory()->published()->create([
            'slug' => 'xss-attempt',
            'body' => '<p>Safe text</p><script>alert(1)</script><a href="javascript:alert(1)">bad link</a><img src=x onerror=alert(1)>',
        ]);

        $this->get('/news/xss-attempt')
            ->assertOk()
            ->assertSee('Safe text')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('onerror', false);
    }

    public function test_unsafe_links_in_sections_are_not_rendered(): void
    {
        Page::factory()->published()->create(['slug' => 'links', 'sections' => [
            ['type' => 'hero', 'data' => ['heading' => 'Links', 'primary_label' => 'Click', 'primary_url' => 'javascript:alert(1)']],
        ]]);

        $this->get('/links')->assertOk()->assertDontSee('javascript:alert', false);
    }

    public function test_navigation_hides_links_to_unpublished_pages(): void
    {
        $this->seed(CmsSeeder::class);

        // URL links are stored as site-relative paths; page links resolve to absolute URLs.
        $this->get('/projects')->assertSee('href="/news"', false)->assertDontSee('href="'.url('/about').'"', false);

        app(ContentWorkflow::class)->saveDraft(Page::query()->where('slug', 'about')->first(), [
            'intro' => 'Approved introduction', 'sections' => [],
        ], $editor = $this->editor());
        app(ContentWorkflow::class)->publish(Page::query()->where('slug', 'about')->first(), $editor);

        $this->get('/projects')->assertSee('href="'.url('/about').'"', false);
    }

    public function test_seo_metadata_sitemap_and_robots(): void
    {
        Page::factory()->published()->create(['slug' => 'about', 'title' => 'About', 'meta_title' => 'About Think Ocean', 'meta_description' => 'Who we are.']);
        Page::factory()->create(['slug' => 'draft-page']);
        Project::factory()->published()->create(['slug' => 'live-project']);
        Project::factory()->create(['slug' => 'hidden-project']);

        $this->get('/about')
            ->assertSee('<title>About Think Ocean | Think Ocean CIC</title>', false)
            ->assertSee('<meta name="description" content="Who we are.">', false)
            ->assertSee('<link rel="canonical" href="'.url('/about').'">', false)
            ->assertSee('property="og:title"', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(url('/about'))
            ->assertSee(url('/projects/live-project'))
            ->assertDontSee('hidden-project')
            ->assertDontSee('draft-page');

        $this->get('/search')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_unknown_urls_get_the_custom_404_page(): void
    {
        $this->get('/this-does-not-exist')->assertNotFound()->assertSee("We couldn't find that page");
        $this->get('/projects/nope')->assertNotFound()->assertSee('Go to the homepage');
    }

    public function test_core_page_aliases_redirect_to_their_canonical_url(): void
    {
        $this->get('/home')->assertRedirect(route('home'));
    }

    public function test_staff_can_preview_drafts_but_the_public_cannot(): void
    {
        $editor = $this->editor();
        $page = Page::factory()->published()->create(['slug' => 'preview-me', 'intro' => 'Live words']);
        app(ContentWorkflow::class)->saveDraft($page->fresh(), ['intro' => 'Draft words'], $editor);

        $url = route('admin.preview', ['type' => 'page', 'id' => $page->id]);

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($editor)->get($url)
            ->assertOk()
            ->assertSee('Draft words')
            ->assertSee('Preview of unpublished changes')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertSame('Live words', $page->fresh()->intro);
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
