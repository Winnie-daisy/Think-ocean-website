<?php

namespace App\Http\Controllers;

use App\Http\Requests\DonationRequest;
use App\Models\Donation;
use App\Services\ActivityLogger;
use App\Services\StripeCheckout;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/** Online donations through Stripe Checkout. */
class DonationController extends Controller
{
    public function __construct(private readonly StripeCheckout $stripe) {}

    public function store(DonationRequest $request, SiteSettings $settings): RedirectResponse
    {
        $back = redirect()->to(url()->previous(route('pages.show', 'donate')).'#donate');

        if ($request->isLikelySpam()) {
            return $back;
        }

        if (! $this->stripe->isConfigured()) {
            return $back->withErrors(['amount' => 'Online donations are not available yet. Please contact us to give.']);
        }

        $donation = new Donation([
            'amount' => $request->amountInMinorUnits(),
            'currency' => strtolower((string) config('services.stripe.currency')),
            'frequency' => $request->validated('frequency'),
            'name' => $request->validated('name') ?: null,
            'email' => $request->validated('email'),
        ]);
        $donation->forceFill([
            'reference' => (string) Str::uuid(),
            'ip_address' => $request->ip(),
        ])->save();

        try {
            $session = $this->stripe->createSession(
                $donation,
                route('donate.thanks').'?session_id={CHECKOUT_SESSION_ID}',
                route('donate.cancelled', $donation->reference),
                ($donation->frequency === 'monthly' ? 'Monthly donation to ' : 'Donation to ').$settings->get('organisation_name'),
            );
        } catch (Throwable $e) {
            Log::error('Stripe checkout failed', ['donation' => $donation->id, 'error' => $e->getMessage()]);
            $donation->forceFill(['status' => 'failed'])->save();

            return $back->withInput()->withErrors(['amount' => 'We could not connect to our payment provider. Please try again in a moment.']);
        }

        $donation->forceFill(['stripe_session_id' => $session['id']])->save();

        return redirect()->away($session['url']);
    }

    /** Stripe sends donors here after paying. The webhook is the source of truth; this just confirms early. */
    public function thanks(Request $request): View
    {
        $donation = null;
        $sessionId = (string) $request->query('session_id', '');

        if ($sessionId !== '' && $this->stripe->isConfigured()) {
            try {
                $donation = $this->stripe->applySession($this->stripe->retrieveSession($sessionId));
            } catch (Throwable $e) {
                Log::warning('Could not confirm Stripe session on return', ['session' => $sessionId, 'error' => $e->getMessage()]);
                $donation = Donation::query()->where('stripe_session_id', $sessionId)->first();
            }
        }

        return view('donate-thanks', ['donation' => $donation]);
    }

    public function cancelled(string $reference): RedirectResponse
    {
        Donation::query()->where('reference', $reference)->where('status', 'pending')->update(['status' => 'cancelled']);

        return redirect()->to(route('pages.show', 'donate').'#donate')
            ->with('donation_status', 'Your donation was cancelled and you have not been charged.');
    }

    public function webhook(Request $request, ActivityLogger $activity): Response
    {
        $event = $this->stripe->verifyWebhook($request->getContent(), $request->header('Stripe-Signature'));

        if (! $event) {
            return response('Invalid signature', 400);
        }

        if (in_array($event['type'] ?? null, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired', 'checkout.session.async_payment_failed'], true)) {
            $session = $event['data']['object'] ?? [];

            if (($event['type'] ?? null) === 'checkout.session.async_payment_failed') {
                Donation::query()->where('stripe_session_id', $session['id'] ?? '')->where('status', 'pending')->update(['status' => 'failed']);
            } else {
                $donation = $this->stripe->applySession($session);

                if ($donation?->wasChanged('status') && $donation->status === 'paid') {
                    $activity->log('donation.paid', 'Received a '.$donation->formattedAmount().' donation', $donation, ['frequency' => $donation->frequency]);
                }
            }
        }

        return response('OK');
    }
}
