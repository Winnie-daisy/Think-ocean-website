@php
    /** @var \App\Models\Page $page */
    $sections = $page->sections ?? [];
    $startsWithHero = ($sections[0]['type'] ?? null) === 'hero';
@endphp

<x-layout
    :title="$page->meta_title ?: $page->title"
    :description="$page->meta_description ?: $page->intro"
    :image="$page->ogImage"
    :canonical="$page->publicUrl()"
    :overlay-header="$startsWithHero"
>
    @unless ($startsWithHero)
        <x-page-header :title="$page->title" :intro="$page->intro" />
    @endunless

    @if ($sections)
        <x-sections :sections="$sections" />
    @elseif (! $page->intro)
        <div class="container-site py-20">
            <x-empty-state title="This page is being prepared">Please check back soon.</x-empty-state>
        </div>
    @else
        <div class="py-10"></div>
    @endif
</x-layout>
