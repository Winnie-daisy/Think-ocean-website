@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'noindex' => false,
    'overlayHeader' => false,
    'type' => 'website',
    'jsonLd' => null,
])

@php
    $siteName = $site->get('organisation_name');
    $fullTitle = $title ? $title.' | '.$siteName : $site->get('default_meta_title', $siteName);
    $metaDescription = \Illuminate\Support\Str::limit(strip_tags((string) ($description ?: $site->get('default_meta_description'))), 300);
    $ogImage = $image instanceof \App\Models\Media ? $image : $site->media('default_og_image_id');
    $ogImageUrl = $ogImage?->urlForWidth(1200);
    $favicon = $site->media('favicon_media_id');
    $canonicalUrl = $canonical ?? url()->current();
    $isPreview = $isPreview ?? false;
@endphp

<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($noindex || $isPreview)
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $type }}">
    <meta property="og:title" content="{{ $title ?: $fullTitle }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:locale" content="en_GB">
    @if ($ogImageUrl)
        <meta property="og:image" content="{{ $ogImageUrl }}">
        @if ($ogImage->alt_text)
            <meta property="og:image:alt" content="{{ $ogImage->alt_text }}">
        @endif
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif

    <meta name="theme-color" content="#0B3D5B">
    @if ($favicon)
        <link rel="icon" href="{{ $favicon->urlForWidth(480) }}">
    @else
        <link rel="icon" type="image/jpeg" href="{{ asset('images/think-ocean/logo.jpg') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/think-ocean/logo.jpg') }}">
    @endif

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($jsonLd)
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only z-[100] rounded-full bg-white px-5 py-3 font-semibold text-navy shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
        Skip to main content
    </a>

    @if ($isPreview)
        <div class="relative z-50 bg-amber-300 px-4 py-2 text-center text-sm font-semibold text-amber-950" role="status">
            Preview of unpublished changes — this page is not visible to the public.
        </div>
    @endif

    {{-- The header is always a solid sticky bar; overlayHeader is accepted for older views but no longer changes it. --}}
    <x-site-header />

    <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>
