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

        $this->get('/')->assertOk()->assertSee('Shared Purpose.')->assertSee('Our Partners')->assertSee('Discover the work')->assertSee('Watch');
        $this->get('/about')->assertOk()->assertSee('An organisation focused on ocean-related change')->assertSee('Our Values')->assertDontSee('Meet the people behind');
        $this->get('/what-we-do')->assertOk()->assertSee('Our Focus Areas');
        $this->get('/get-involved')->assertOk()->assertSee('Be Part of the Change')->assertSee('Send Enquiry');
        $this->get('/donate')->assertOk()->assertSee('Your support can enable')->assertSee('Online donations are coming soon');
        $this->get('/projects')->assertOk()->assertSee('Reef Restoration Initiative');
        $this->get('/news')->assertOk()->assertSee('How blue carbon helps fight climate change');
        $this->get('/privacy-policy')->assertOk()->assertSee('Information we receive');
        $this->get('/watch')->assertOk()->assertSee('Live now')->assertSee('data-live-player', false);
        $this->get('/live')->assertRedirect('/watch');
    }

    public function test_the_import_can_be_run_again_without_duplicating_content(): void
    {
        $admin = User::factory()->admin()->create();

        $this->artisan('cms:import-think-ocean', ['--user' => $admin->email])->assertSuccessful();
        $this->artisan('cms:import-think-ocean', ['--user' => $admin->email])->assertSuccessful();

        $this->assertSame(5, Project::query()->count());
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
