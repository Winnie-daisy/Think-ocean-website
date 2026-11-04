<?php

namespace Tests\Feature\PublicSite;

use App\Models\ContactSubmission;
use App\Models\Donation;
use App\Models\Page;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_123',
            'services.stripe.webhook_secret' => 'whsec_test',
            'services.stripe.currency' => 'gbp',
        ]);

        Page::factory()->published()->create([
            'slug' => 'donate',
            'title' => 'Support Our Work',
            'sections' => [['type' => 'donation_form', 'data' => ['heading' => 'Make a donation', 'amounts' => '10, 25, 50']]],
        ]);
    }

    public function test_the_donate_page_shows_the_form_when_stripe_is_configured(): void
    {
        $this->get('/donate')->assertOk()->assertSee('Continue to secure payment')->assertSee('£25');
    }

    public function test_a_donation_redirects_to_stripe_checkout(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1'])]);

        $this->post('/donate', ['amount' => '25', 'frequency' => 'one_off', 'email' => 'donor@example.com', 'name' => 'Ada'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');

        $donation = Donation::query()->sole();
        $this->assertSame(2500, $donation->amount);
        $this->assertSame('gbp', $donation->currency);
        $this->assertSame('pending', $donation->status);
        $this->assertSame('cs_test_1', $donation->stripe_session_id);

        Http::assertSent(fn (Request $request) => $request['mode'] === 'payment'
            && $request['line_items'][0]['price_data']['unit_amount'] === 2500
            && $request['customer_email'] === 'donor@example.com');
    }

    public function test_a_custom_monthly_amount_starts_a_subscription(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_test_2', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_2'])]);

        $this->post('/donate', ['amount' => '10', 'custom_amount' => '£12.50', 'frequency' => 'monthly', 'email' => 'donor@example.com'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_2');

        $this->assertSame(1250, Donation::query()->sole()->amount);
        Http::assertSent(fn (Request $request) => $request['mode'] === 'subscription'
            && $request['line_items'][0]['price_data']['recurring']['interval'] === 'month');
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        Http::fake();

        $this->from('/donate')->post('/donate', ['amount' => '0.50', 'frequency' => 'one_off', 'email' => 'donor@example.com'])
            ->assertRedirect('/donate')
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Donation::query()->count());
        Http::assertNothingSent();
    }

    public function test_a_signed_webhook_marks_the_donation_paid(): void
    {
        $donation = $this->pendingDonation();

        $this->postSignedWebhook([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_9', 'payment_status' => 'paid', 'payment_intent' => 'pi_1', 'mode' => 'payment']],
        ])->assertOk();

        $donation->refresh();
        $this->assertSame('paid', $donation->status);
        $this->assertSame('pi_1', $donation->stripe_payment_intent);
        $this->assertNotNull($donation->paid_at);
    }

    public function test_an_unsigned_webhook_is_ignored(): void
    {
        $donation = $this->pendingDonation();

        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=forged', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_9', 'payment_status' => 'paid']]]))
            ->assertStatus(400);

        $this->assertSame('pending', $donation->refresh()->status);
    }

    public function test_the_thank_you_page_confirms_a_paid_session(): void
    {
        $donation = $this->pendingDonation();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_9' => Http::response(['id' => 'cs_test_9', 'payment_status' => 'paid', 'mode' => 'payment'])]);

        $this->get('/donate/thank-you?session_id=cs_test_9')->assertOk()->assertSee('£25.00');
        $this->assertSame('paid', $donation->refresh()->status);
    }

    public function test_the_footer_newsletter_stores_each_address_once(): void
    {
        $this->post('/subscribe', ['email' => 'Fan@Example.com'])->assertRedirect()->assertSessionHas('subscribed');
        $this->post('/subscribe', ['email' => 'fan@example.com'])->assertRedirect();

        $this->assertSame(['fan@example.com'], Subscriber::query()->pluck('email')->all());
    }

    public function test_the_get_involved_enquiry_is_saved_without_a_message(): void
    {
        $this->travel(-10)->seconds();
        $started = Crypt::encryptString((string) now()->timestamp);
        $this->travelBack();

        $this->post('/contact', [
            'source' => 'get-involved',
            'form_started' => $started,
            'name' => 'Sam',
            'email' => 'sam@example.com',
            'subject' => 'Volunteering',
            'consent' => '1',
        ])->assertRedirect(route('get-involved').'#help');

        $this->assertStringContainsString('Volunteering', ContactSubmission::query()->sole()->message);
    }

    private function pendingDonation(): Donation
    {
        $donation = new Donation(['amount' => 2500, 'currency' => 'gbp', 'frequency' => 'one_off', 'email' => 'donor@example.com']);
        $donation->forceFill(['reference' => (string) str()->uuid(), 'stripe_session_id' => 'cs_test_9'])->save();

        return $donation;
    }

    private function postSignedWebhook(array $event)
    {
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
