@php
    $image = $media->get((int) ($data['image_id'] ?? 0));
    $tag = $index === 0 ? 'h1' : 'h2';
    // "Healthy Oceans. Shared Purpose. Collective Action." → one sentence per line.
    $lines = preg_split('/(?<=[.!?])\s+/', trim((string) ($data['heading'] ?? '')));
@endphp

<section class="relative isolate flex min-h-[min(600px,86vh)] items-center overflow-hidden bg-navy-deep text-white">
    @if ($image)
        <x-media-image :media="$image" :eager="$index === 0" sizes="100vw" :width="1920" alt=""
                       class="absolute inset-0 -z-20 size-full object-cover object-[center_45%]" />
    @else
        <img src="{{ asset('images/think-ocean/home-hero.jpg') }}" alt="" class="absolute inset-0 -z-20 size-full object-cover object-[center_45%]" @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif>
    @endif
    <div class="photo-overlay absolute inset-0 -z-10"></div>

    <div class="container-site pb-24 pt-20">
        <{{ $tag }} class="max-w-[640px] text-[clamp(38px,5.4vw,62px)] font-bold leading-[1.08] tracking-[-0.015em] text-white drop-shadow-[0_2px_14px_rgb(0_0_0/0.25)]">
            @foreach ($lines as $line)
                <span class="block">{{ $line }}</span>
            @endforeach
        </{{ $tag }}>

        @if (filled($data['text'] ?? null))
            <p class="mt-5 max-w-[460px] text-base font-medium text-[#e8f6fa] sm:text-lg">{{ $data['text'] }}</p>
        @endif

        @if (filled($data['primary_label'] ?? null) || filled($data['secondary_label'] ?? null))
            <div class="mt-8 flex flex-wrap gap-3.5">
                @if (filled($data['primary_label'] ?? null) && filled($data['primary_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['primary_url']))
                    <a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }}</a>
                @endif
                @if (filled($data['secondary_label'] ?? null) && filled($data['secondary_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['secondary_url']))
                    <a href="{{ $data['secondary_url'] }}" class="btn btn-outline-light">{{ $data['secondary_label'] }}</a>
                @endif
            </div>
        @endif
    </div>

    @if ($index === 0)
        <a href="#after-hero" class="absolute bottom-6 left-1/2 grid size-10 -translate-x-1/2 place-items-center rounded-full border border-white/60 text-white motion-safe:animate-[scroll-bob_2.4s_ease-in-out_infinite]" aria-label="Scroll to content">
            <x-ocean-icon name="chevron-down" class="size-5" />
        </a>
    @endif
</section>
@if ($index === 0)
    <span id="after-hero" class="block"></span>
@endif
