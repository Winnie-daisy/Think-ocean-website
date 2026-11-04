@php
    /** @var \App\Models\Page|null $page */
    $site = app(\App\Support\SiteSettings::class);

    // Until a home page is published, show a minimal version built only
    // from approved brand copy and live projects/news.
    $sections = $page?->sections ?: [
        ['type' => 'hero', 'data' => [
            'heading' => $site->get('tagline'),
            'primary_label' => 'Explore Our Work', 'primary_url' => route('projects.index', absolute: false),
            'secondary_label' => 'Get in touch', 'secondary_url' => route('contact', absolute: false),
        ]],
        ['type' => 'featured_projects', 'data' => []],
        ['type' => 'latest_news', 'data' => []],
    ];

    $organisation = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $site->get('organisation_name'),
        'url' => route('home'),
        'slogan' => $site->get('tagline'),
        'logo' => $site->media('logo_media_id')?->url(),
        'email' => $site->get('contact_email'),
        'sameAs' => array_column($site->socialLinks(), 'url') ?: null,
    ]);
@endphp

<x-layout
    :title="$page?->meta_title"
    :description="$page?->meta_description ?: $page?->intro"
    :image="$page?->ogImage"
    :canonical="route('home')"
    :overlay-header="($sections[0]['type'] ?? null) === 'hero'"
    :json-ld="$organisation"
>
    @if (($sections[0]['type'] ?? null) !== 'hero')
        <x-page-header :title="$page?->title ?? $site->get('organisation_name')" :intro="$page?->intro" />
    @endif

    <x-sections :sections="$sections" />
</x-layout>
