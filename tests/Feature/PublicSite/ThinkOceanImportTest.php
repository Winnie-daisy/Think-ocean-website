<?php

namespace Tests\Feature\PublicSite;

use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use App\Support\LiveStream;
use App\Support\SiteSettings;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThinkOceanImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(CmsSeeder::class);
    }

    public function test_the_import_publishes_every_think_ocean_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->artisan('cms:import-think-ocean', ['--user' => $admin->email])->assertSuccessful();

        $this->assertSame(5, Project::query()->published()->count());
        $this->assertSame(7, Post::query()->published()->count());
        $this->assertTrue(Page::slugIsLive('donate'));

        $this->get('/')->assertOk()->assertSee('Shared Purpose.')->assertSee('Five missions.')->assertSee('Our Partners')->assertSee('Eight Partners Media')->assertSee('the Voice of the Planet');
        $this->get('/about')->assertOk()->assertSee('Rooted in action since 1992')->assertSee('Southern Ocean Whale Sanctuary')->assertSee('Impact-Oriented')->assertSee('Hugo Valdes-Vera');
        $this->get('/what-we-do')->assertOk()->assertSee('Our Five Missions')->assertSee('the bubble wall', false);
        $this->get('/get-involved')->assertOk()->assertSee('Be Part of the Change')->assertSee('Content, volunteers and sponsors.')->assertSee('mailto:TV&#64;thinkocean.earth', false)->assertSee('Send Enquiry');
        $this->get('/donate')->assertOk()->assertSee('Your support can enable')->assertSee('Online donations are coming soon');
        $this->get('/projects')->assertOk()->assertSee('Stop at Source: River Plastic Innovation')->assertSee('The Mini Statues of London');
        $this->get('/projects/plastic-free-campus')->assertOk()->assertSee('Change management');
        $this->get('/news')->assertOk()->assertSee('How blue carbon helps fight climate change');
        $this->get('/privacy-policy')->assertOk()->assertSee('Information we receive');
        $this->get('/watch')->assertOk()->assertSee('Live now')->assertSee('data-live-player', false)->assertSee('Think Ocean TV Originals')->assertSee('The Planet Questions');
        $this->get('/think-ocean-tv')->assertRedirect(route('watch'));
        $this->get('/contact')->assertOk()->assertSee('TV@thinkocean.earth');
        $this->get('/live')->assertRedirect('/watch');
    }

    public function test_the_import_can_be_run_again_without_duplicating_content(): void
    {
        $admin = User::factory()->admin()->create();

        Project::factory()->published()->create(['slug' => 'reef-restoration-initiative', 'title' => 'Reef Restoration Initiative']);

        $this->artisan('cms:import-think-ocean', ['--user' => $admin->email])->assertSuccessful();
        $this->artisan('cms:import-think-ocean', ['--user' => $admin->email])->assertSuccessful();

        // The prototype's sample project is replaced by the five real missions (and stays restorable).
        $this->assertSame(5, Project::query()->count());
        $this->assertTrue(Project::onlyTrashed()->where('slug', 'reef-restoration-initiative')->exists());
        $this->assertSame(7, Post::query()->count());
    }

    public function test_the_import_requires_a_user_who_can_publish(): void
    {
        $contributor = User::factory()->contributor()->create();

        $this->artisan('cms:import-think-ocean', ['--user' => $contributor->email])->assertFailed();
        $this->assertFalse(Page::slugIsLive('about'));
    }

    public function test_the_live_page_is_off_air_until_switched_on(): void
    {
        $this->get('/watch')->assertOk()->assertSee('Off air')->assertDontSee('data-live-player', false);

        app(SiteSettings::class)->set(['live_enabled' => true, 'live_stream_url' => 'https://www.youtube.com/watch?v=abc123']);

        $this->get('/watch')->assertOk()->assertSee('Live now')->assertSee('youtube-nocookie.com/embed/abc123', false);
    }

    public function test_stream_links_are_recognised(): void
    {
        $this->assertSame('hls', LiveStream::fromUrl('https://example.com/live/play.m3u8')['type']);
        $this->assertSame('video', LiveStream::fromUrl('https://example.com/clip.mp4')['type']);
        $this->assertSame('https://player.vimeo.com/video/123?autoplay=1&muted=1', LiveStream::fromUrl('https://vimeo.com/123')['src']);
        $this->assertNull(LiveStream::fromUrl('not a url'));
    }
}
