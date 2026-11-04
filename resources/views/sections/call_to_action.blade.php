@php
    $image = $media->get((int) ($data['image_id'] ?? 0));
    $hasPrimary = filled($data['primary_label'] ?? null) && filled($data['primary_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['primary_url']);
    $hasSecondary = filled($data['secondary_label'] ?? null) && filled($data['secondary_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['secondary_url']);
@endphp

@if ($image)
    {{-- Full-width photo strip with the message on the left and the button on the right. --}}
    <section class="relative isolate overflow-hidden bg-navy text-white">
        <x-media-image :media="$image" sizes="100vw" :width="1920" alt="" class="absolute inset-0 -z-20 size-full object-cover" />
        <div class="photo-overlay absolute inset-0 -z-10"></div>
        <div class="container-site flex flex-col items-start gap-6 py-14 md:flex-row md:items-center md:justify-between md:py-16" data-reveal>
            <div>
                <h2 class="text-[clamp(26px,3vw,36px)] font-bold text-white">{{ $data['heading'] ?? '' }}</h2>
                @if (filled($data['text'] ?? null))
                    <p class="mt-2 max-w-[520px] text-[#e2f3f7]">{{ $data['text'] }}</p>
                @endif
            </div>
            @if ($hasPrimary || $hasSecondary)
                <div class="flex flex-wrap gap-3 md:flex-none">
                    @if ($hasPrimary)
                        <a href="{{ $data['primary_url'] }}" class="btn btn-primary">{{ $data['primary_label'] }}</a>
                    @endif
                    @if ($hasSecondary)
                        <a href="{{ $data['secondary_url'] }}" class="btn btn-outline-light">{{ $data['secondary_label'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </section>
@else
    {{-- Light-blue band with the wave mark. --}}
    <section class="section">
        <div class="container-site" data-reveal>
            <div class="tile flex flex-col items-start gap-6 px-6 py-7 md:flex-row md:items-center md:px-10">
                <x-wave-mark class="h-10 w-16 flex-none text-teal" />
                <div>
                    <h2 class="text-xl font-bold">{{ $data['heading'] ?? '' }}</h2>
                    @if (filled($data['text'] ?? null))
                        <p class="mt-1 max-w-[560px] text-slate-muted">{{ $data['text'] }}</p>
                    @endif
                </div>
                @if ($hasPrimary || $hasSecondary)
                    <div class="flex flex-wrap gap-3 md:ml-auto md:flex-none">
                        @if ($hasPrimary)
                            <a href="{{ $data['primary_url'] }}" class="btn btn-primary btn-sm">{{ $data['primary_label'] }}</a>
                        @endif
                        @if ($hasSecondary)
                            <a href="{{ $data['secondary_url'] }}" class="btn btn-outline btn-sm">{{ $data['secondary_label'] }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
