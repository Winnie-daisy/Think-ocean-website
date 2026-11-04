@props(['post', 'headingLevel' => 'h3'])

@php
    // Bundled photographs used until an article has its own featured image.
    $fallbacks = ['news-turtle', 'news-cleanup', 'news-fish', 'news-boat', 'news-mangrove', 'news-diver'];
    $category = $post->relationLoaded('categories') ? $post->categories->first()?->name : null;
@endphp

<article {{ $attributes->class(['card group relative']) }}>
    <div class="aspect-[16/10] overflow-hidden bg-mist">
        @if ($post->featuredImage)
            <x-media-image :media="$post->featuredImage" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" :width="960" class="card-image size-full object-cover" alt="" />
        @else
            <img src="{{ asset('images/think-ocean/'.$fallbacks[$post->id % count($fallbacks)].'.jpg') }}" alt="" loading="lazy" class="card-image size-full object-cover">
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 px-6 pb-6 pt-[22px]">
        @if ($category)
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-teal-deep">{{ $category }}</p>
        @endif
        <{{ $headingLevel }} class="text-base font-bold leading-snug">
            <a href="{{ $post->publicUrl() }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $post->title }}</a>
        </{{ $headingLevel }}>
        @if ($post->published_at)
            <time datetime="{{ $post->published_at->toDateString() }}" class="text-[13px] text-slate-muted">{{ $post->published_at->format('j M Y') }}</time>
        @endif
        <span class="link-arrow mt-auto pt-2" aria-hidden="true">Read More <x-ocean-icon name="arrow-right" class="size-4" /></span>
    </div>
    {{-- Keyboard focus ring for the whole card (the link is stretched over it). --}}
    <span class="pointer-events-none absolute inset-0 rounded-[14px] ring-teal group-has-[a:focus-visible]:ring-4"></span>
</article>
