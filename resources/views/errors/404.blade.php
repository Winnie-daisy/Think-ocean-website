<x-layout title="Page not found" noindex>
    <x-page-header title="We couldn't find that page" eyebrow="Error 404"
                   intro="The page may have moved, or the link may be out of date.">
        <div class="mt-10 flex flex-wrap gap-4">
            <a href="{{ route('home') }}" class="btn btn-light">Go to the homepage</a>
            <a href="{{ route('search') }}" class="btn btn-outline-light"><x-ocean-icon name="magnifying-glass" class="size-4" /> Search the site</a>
        </div>
    </x-page-header>
    <div class="py-16"></div>
</x-layout>
