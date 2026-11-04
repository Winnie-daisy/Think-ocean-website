<?php

namespace Tests\Feature\Admin;

use App\Models\ContactSubmission;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminUrls(): array
    {
        return [
            'dashboard' => ['/admin'],
            'pages' => ['/admin/pages'],
            'projects' => ['/admin/projects'],
            'posts' => ['/admin/posts'],
            'media' => ['/admin/media'],
            'users' => ['/admin/users'],
            'settings' => ['/admin/settings'],
            'contact submissions' => ['/admin/contact-submissions'],
            'activity log' => ['/admin/activity-logs'],
        ];
    }

    #[DataProvider('adminUrls')]
    public function test_guests_are_redirected_to_the_login_page(string $url): void
    {
        $this->get($url)->assertRedirect('/admin/login');
    }

    public function test_guests_cannot_reach_staff_helper_routes(): void
    {
        $page = Page::factory()->create();

        $this->get(route('admin.preview', ['type' => 'page', 'id' => $page->id]))->assertRedirect('/admin/login');
    }

    public function test_inactive_users_cannot_use_the_panel(): void
    {
        $user = $this->admin();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach ([$this->admin(), $this->editor(), $this->contributor(), $this->reviewer()] as $user) {
            $this->actingAs($user)->get('/admin')->assertOk();
        }
    }

    public function test_only_administrators_manage_users_settings_and_the_activity_log(): void
    {
        foreach ([$this->editor(), $this->contributor(), $this->reviewer()] as $user) {
            $this->actingAs($user)->get('/admin/users')->assertForbidden();
            $this->actingAs($user)->get('/admin/settings')->assertForbidden();
            $this->actingAs($user)->get('/admin/activity-logs')->assertForbidden();
        }

        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/activity-logs')->assertOk();
    }

    public function test_contact_submissions_are_only_visible_to_editors_and_administrators(): void
    {
        $submission = ContactSubmission::factory()->create();

        foreach ([$this->contributor(), $this->reviewer()] as $user) {
            $this->actingAs($user)->get('/admin/contact-submissions')->assertForbidden();
            $this->actingAs($user)->get("/admin/contact-submissions/{$submission->id}")->assertForbidden();
        }

        $this->actingAs($this->editor())->get("/admin/contact-submissions/{$submission->id}")->assertOk()->assertSee($submission->email);
    }

    public function test_reviewers_can_view_but_not_edit_or_create_content(): void
    {
        $reviewer = $this->reviewer();
        $page = Page::factory()->create();

        $this->actingAs($reviewer)->get('/admin/pages')->assertOk();
        $this->actingAs($reviewer)->get("/admin/pages/{$page->id}")->assertOk();
        $this->actingAs($reviewer)->get("/admin/pages/{$page->id}/edit")->assertForbidden();
        $this->actingAs($reviewer)->get('/admin/pages/create')->assertForbidden();
    }

    public function test_admin_pages_are_not_indexed(): void
    {
        $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
