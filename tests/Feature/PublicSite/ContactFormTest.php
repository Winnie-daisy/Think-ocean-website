<?php

namespace Tests\Feature\PublicSite;

use App\Models\ContactSubmission;
use App\Notifications\NewContactSubmission;
use App\Support\SiteSettings;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.org',
            'subject' => 'Volunteering',
            'message' => 'I would like to know more about your work.',
            'consent' => '1',
            'website' => '',
            'form_started' => Crypt::encryptString((string) now()->subSeconds(30)->timestamp),
        ], $overrides);
    }

    public function test_the_contact_page_renders_an_accessible_form(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('<label for="field-name"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_a_valid_message_is_stored(): void
    {
        $this->post('/contact', $this->validData())
            ->assertRedirect('/contact')
            ->assertSessionHas('status');

        $submission = ContactSubmission::query()->sole();
        $this->assertSame('alex@example.org', $submission->email);
        $this->assertSame('unread', $submission->status);
        $this->assertNotNull($submission->ip_address);
    }

    public function test_validation_errors_are_reported(): void
    {
        $this->post('/contact', $this->validData(['name' => '', 'email' => 'not-an-email', 'message' => 'short', 'consent' => null]))
            ->assertSessionHasErrors(['name', 'email', 'message', 'consent']);

        $this->assertSame(0, ContactSubmission::query()->count());

        $this->followingRedirects()
            ->from('/contact')
            ->post('/contact', $this->validData(['email' => 'nope']))
            ->assertSee('There is a problem with your message')
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_input_is_escaped_when_shown_back(): void
    {
        $this->followingRedirects()
            ->from('/contact')
            ->post('/contact', $this->validData(['name' => '<script>alert(1)</script>', 'email' => 'bad']))
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_honeypot_submissions_are_silently_discarded(): void
    {
        $this->post('/contact', $this->validData(['website' => 'http://spam.example']))
            ->assertRedirect('/contact')
            ->assertSessionHas('status');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_instant_or_tampered_submissions_are_discarded(): void
    {
        $this->post('/contact', $this->validData(['form_started' => Crypt::encryptString((string) now()->timestamp)]));
        $this->post('/contact', $this->validData(['form_started' => 'tampered']));

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_submissions_are_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/contact', $this->validData())->assertRedirect();
        }

        $this->post('/contact', $this->validData())->assertStatus(429);
        $this->assertSame(3, ContactSubmission::query()->count());
    }

    public function test_csrf_protection_applies_to_the_form(): void
    {
        // Laravel skips the CSRF check while running tests, so assert that the
        // middleware is on the route instead of exercising it.
        $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName('contact.store'));

        $this->assertContains(PreventRequestForgery::class, $middleware);
    }

    public function test_staff_are_notified_when_a_recipient_is_configured(): void
    {
        Notification::fake();
        app(SiteSettings::class)->set(['contact_notification_email' => 'team@example.org']);

        $this->post('/contact', $this->validData());

        Notification::assertSentOnDemand(NewContactSubmission::class);
    }

    public function test_the_form_can_be_disabled(): void
    {
        app(SiteSettings::class)->set(['contact_form_enabled' => false]);

        $this->get('/contact')->assertOk()->assertSee('Our contact form is currently unavailable');
        $this->post('/contact', $this->validData())->assertNotFound();
    }

    public function test_submissions_are_not_reachable_through_public_routes(): void
    {
        $submission = ContactSubmission::factory()->create(['message' => 'Private message body']);

        $this->get('/contact-submissions')->assertNotFound();
        $this->get("/admin/contact-submissions/{$submission->id}")->assertRedirect('/admin/login');
        $this->get('/search?q=Private+message')->assertDontSee('Private message body');
    }
}
