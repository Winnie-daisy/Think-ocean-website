@php
    /** @var \App\Models\Donation|null $donation */
    $paid = $donation?->status === 'paid';
@endphp

<x-layout title="Thank you" noindex>
    <x-page-header title="Thank you" :intro="$paid ? 'Your support helps protect our ocean.' : 'We are confirming your donation.'" fallback="get-involved-banner" />

    <section class="section">
        <div class="container-narrow text-center">
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-mist text-teal-deep">
                <x-ocean-icon :name="$paid ? 'heart' : 'clock'" class="size-8" />
            </span>
            @if ($paid)
                <h2 class="section-title mt-5">Thank you for your {{ $donation->frequency === 'monthly' ? 'monthly ' : '' }}donation of {{ $donation->formattedAmount() }}</h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-muted">A receipt has been sent to {{ $donation->email }} by our payment provider, Stripe. Thank you for helping us create lasting change for the ocean.</p>
            @else
                <h2 class="section-title mt-5">Your payment is being processed</h2>
                <p class="mx-auto mt-3 max-w-xl text-slate-muted">If your payment went through, you will receive a receipt by email shortly. If you have any questions, please contact us.</p>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('projects.index') }}" class="btn btn-primary">See our projects</a>
                <a href="{{ route('home') }}" class="btn btn-outline">Back to the homepage</a>
            </div>
        </div>
    </section>
</x-layout>
