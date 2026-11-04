@props([
    'title',
    'intro' => null,
    'eyebrow' => null,
    'image' => null,
    'fallback' => null,
])

{{--
    Photo banner for interior pages. Uses the media-library image when one is
    supplied, otherwise a bundled photograph chosen by the page's URL.
--}}
@php
    $bundled = [
        'about' => 'about-hero',
        'what-we-do' => 'what-we-do-hero',
        'projects' => 'projects-hero',
        'get-involved' => 'get-involved-hero',
        'news' => 'news-hero',
        'contact' => 'contact-hero',
        'donate' => 'get-involved-banner',
        'watch' => 'news-fish',
    ];
    $fallbackImage = asset('images/think-ocean/'.($fallback ?? $bundled[request()->segment(1)] ?? 'about-hero').'.jpg');
@endphp

<section class="relative isolate overflow-hidden bg-navy text-white">
    @if ($image)
        <x-media-image :media="$image" eager class="absolute inset-0 -z-20 size-full object-cover" alt="" />
    @else
        <img src="{{ $fallbackImage }}" alt="" class="absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
    @endif
    <div class="banner-overlay absolute inset-0 -z-10"></div>

    <div class="container-site flex min-h-[240px] flex-col justify-center py-14 sm:min-h-[300px] sm:py-16">
        <div class="max-w-3xl">
            {{ $before ?? '' }}
            @if ($eyebrow)
                <p class="eyebrow eyebrow-light">{{ $eyebrow }}</p>
            @endif
            <h1 class="text-[clamp(34px,4.8vw,54px)] font-bold tracking-[-0.01em] text-white drop-shadow-[0_2px_12px_rgb(0_0_0/0.25)]">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-3 max-w-[560px] text-base text-[#e2f3f7] sm:text-lg">{{ $intro }}</p>
            @endif
            {{ $slot }}
        </div>
    </div>
</section>
