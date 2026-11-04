@props(['platform'])

{{-- Outline brand marks for the social platforms offered in Site settings; decorative. --}}
@php
    $paths = [
        'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
        'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
        'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/>',
        'youtube' => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
        'x' => '<path d="M4 4l16 16"/><path d="M20 4 4 20"/>',
        'bluesky' => '<path d="M12 11c-1.5-3-5-7-8-7-1 0-1 1.5-1 3 0 2 1 5 4 5.5-3 .5-4 2.5-2 4.5 3 3 5-1 7-4 2 3 4 7 7 4 2-2 1-4-2-4.5 3-.5 4-3.5 4-5.5 0-1.5 0-3-1-3-3 0-6.5 4-8 7z"/>',
        'tiktok' => '<path d="M9 12a4 4 0 1 0 4 4V2a5 5 0 0 0 5 5"/>',
    ];
@endphp

<svg {{ $attributes->class(['size-[18px]']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$platform] ?? '<circle cx="12" cy="12" r="9"/>' !!}</svg>
