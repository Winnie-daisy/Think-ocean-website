<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * A donation started on the website. Payment is taken by Stripe Checkout; the
 * record is only marked paid once Stripe confirms it (webhook or return page).
 */
#[Fillable(['amount', 'currency', 'frequency', 'name', 'email'])]
#[Hidden(['ip_address'])]
class Donation extends Model
{
    public const STATUSES = [
        'pending' => 'Awaiting payment',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
        'failed' => 'Failed',
    ];

    public const FREQUENCIES = [
        'one_off' => 'One-off',
        'monthly' => 'Monthly',
    ];

    protected $attributes = [
        'status' => 'pending',
        'frequency' => 'one_off',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** Amount in major units for display, e.g. "£25.00". */
    public function formattedAmount(): string
    {
        return Number::currency($this->amount / 100, strtoupper($this->currency), 'en_GB');
    }

    public function markPaid(?string $paymentIntent = null, ?string $subscription = null): void
    {
        if ($this->status === 'paid') {
            return;
        }

        $this->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
            'stripe_payment_intent' => $paymentIntent ?? $this->stripe_payment_intent,
            'stripe_subscription' => $subscription ?? $this->stripe_subscription,
        ])->save();
    }
}
