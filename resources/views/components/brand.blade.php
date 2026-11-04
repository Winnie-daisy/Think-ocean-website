@props(['light' => false, 'stacked' => false])

{{-- The logo uploaded in Site settings, or the bundled Think Ocean CIC logo until one is chosen. --}}
@php
    $site = app(\App\Support\SiteSettings::class);
    $logo = $site->media('logo_media_id');
    $size = $stacked ? 96 : 52;
@endphp

<a href="{{ route('home') }}" {{ $attributes->class(['inline-flex shrink-0 items-center']) }} aria-label="{{ $site->get('organisation_name') }} — home">
    @if ($logo)
        <img src="{{ $logo->urlForWidth(480) }}" alt="" class="w-auto object-contain" style="height: {{ $size }}px" width="{{ $logo->width }}" height="{{ $logo->height }}">
    @else
        <img src="{{ asset('images/think-ocean/logo.jpg') }}" alt="" width="{{ $size }}" height="{{ $size }}"
             @class(['object-contain', 'rounded-md' => $stacked, 'rounded-sm' => ! $stacked])
             style="width: {{ $size }}px; height: {{ $size }}px">
    @endif
</a>
