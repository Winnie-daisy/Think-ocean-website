<?php

namespace App\Services;

use App\Models\Donation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Stripe Checkout client for donations. Card details are entered on
 * Stripe's hosted page, so they never touch this application.
 */
class StripeCheckout
{
    private const API = 'https://api.stripe.com/v1';

    /** Seconds a webhook signature stays valid, to stop replayed events. */
    private const SIGNATURE_TOLERANCE = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    /**
     * @return array{id: string, url: string}
     */
    public function createSession(Donation $donation, string $successUrl, string $cancelUrl, string $description): array
    {
        $monthly = $donation->frequency === 'monthly';

        $params = array_filter([
            'mode' => $monthly ? 'subscription' : 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'customer_email' => $donation->email,
            'client_reference_id' => $donation->reference,
            'submit_type' => $monthly ? null : 'donate',
            'metadata' => ['donation' => $donation->reference],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => array_filter([
                    'currency' => $donation->currency,
                    'unit_amount' => $donation->amount,
                    'product_data' => ['name' => $description],
                    'recurring' => $monthly ? ['interval' => 'month'] : null,
                ]),
            ]],
        ], fn ($value) => $value !== null);

        $response = $this->client()->asForm()->post(self::API.'/checkout/sessions', $params);

        if ($response->failed() || ! $response->json('url')) {
            throw new RuntimeException('Stripe could not start a checkout session: '.$response->json('error.message', $response->status()));
        }

        return ['id' => $response->json('id'), 'url' => $response->json('url')];
    }

    /** @return array<string, mixed> */
    public function retrieveSession(string $id): array
    {
        $response = $this->client()->get(self::API.'/checkout/sessions/'.rawurlencode($id));

        if ($response->failed()) {
            throw new RuntimeException('Stripe could not find checkout session '.$id);
        }

        return $response->json();
    }

    /**
     * Verify a webhook's Stripe-Signature header and return the decoded event.
     *
     * @return array<string, mixed>|null null when the signature is missing, wrong or too old
     */
    public function verifyWebhook(string $payload, ?string $header): ?array
    {
        $secret = (string) config('services.stripe.webhook_secret');

        if ($secret === '' || ! $header) {
            return null;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || abs(time() - $timestamp) > self::SIGNATURE_TOLERANCE) {
            return null;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return json_decode($payload, true) ?: null;
            }
        }

        return null;
    }

    /** Apply a completed or expired Checkout session to its donation. */
    public function applySession(array $session): ?Donation
    {
        $donation = Donation::query()
            ->where('stripe_session_id', $session['id'] ?? '')
            ->orWhere('reference', $session['client_reference_id'] ?? '')
            ->first();

        if (! $donation) {
            return null;
        }

        // One-off payments report payment_status "paid"; a started subscription completes with its first invoice paid.
        $paid = ($session['payment_status'] ?? null) === 'paid'
            || (($session['status'] ?? null) === 'complete' && ($session['mode'] ?? null) === 'subscription');

        if ($paid) {
            $donation->markPaid($session['payment_intent'] ?? null, $session['subscription'] ?? null);
        } elseif (($session['status'] ?? null) === 'expired' && $donation->status === 'pending') {
            $donation->forceFill(['status' => 'cancelled'])->save();
        }

        return $donation;
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 200, throw: false);
    }
}
