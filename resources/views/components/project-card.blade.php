@props(['project', 'headingLevel' => 'h3'])

@php
    // Bundled photographs used until a project has its own featured image.
    $fallbacks = ['project-reef', 'project-fisheries', 'project-blue-carbon', 'project-education'];
@endphp

<article {{ $attributes->class(['card group relative']) }}>
    <div class="relative aspect-[16/10] overflow-hidden bg-mist">
        @if ($project->featuredImage)
            <x-media-image :media="$project->featuredImage" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" :width="960" class="card-image size-full object-cover" alt="" />
        @else
            <img src="{{ asset('images/think-ocean/'.$fallbacks[$project->id % count($fallbacks)].'.jpg') }}" alt="" loading="lazy" class="card-image size-full object-cover">
        @endif
        @if ($project->project_status)
            <span class="absolute left-4 top-4 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-navy shadow-sm">{{ $project->project_status->getLabel() }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 px-6 pb-6 pt-[22px]">
        @if ($project->category || $project->location)
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-teal-deep">
                {{ collect([$project->category?->name, $project->location])->filter()->implode(' · ') }}
            </p>
        @endif
        <{{ $headingLevel }} class="text-base font-bold leading-snug">
            <a href="{{ $project->publicUrl() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $project->title }}</a>
        </{{ $headingLevel }}>
        @if ($project->summary)
            <p class="line-clamp-3 text-sm text-slate-muted">{{ $project->summary }}</p>
        @endif
        <span class="link-arrow mt-auto pt-2" aria-hidden="true">View Project <x-ocean-icon name="arrow-right" class="size-4" /></span>
    </div>
    {{-- Keyboard focus ring for the whole card (the link is stretched over it). --}}
    <span class="pointer-events-none absolute inset-0 rounded-[14px] ring-teal group-has-[a:focus-visible]:ring-4"></span>
</article>
