@php
    $image = $media->get((int) ($data['image_id'] ?? 0));
    $imageLeft = ($data['image_position'] ?? 'right') === 'left';
    // A heading written as two sentences ("The ocean matters. So does what we do next.") breaks onto two lines.
    $lines = preg_split('/(?<=[.!?])\s+/', trim((string) ($data['heading'] ?? '')));
@endphp

<section class="section">
    <div class="container-site grid items-center gap-9 lg:grid-cols-2 lg:gap-16">
        @if ($image)
            <figure @class(['lg:order-last' => ! $imageLeft]) data-reveal>
                <x-media-image :media="$image" sizes="(min-width: 1024px) 50vw, 100vw" :width="1440" class="aspect-[16/10] w-full rounded-[18px] object-cover shadow-card" />
                @if ($image->caption || $image->credit)
                    <figcaption class="mt-3 text-sm text-slate-muted">
                        {{ $image->caption }}@if ($image->caption && $image->credit) · @endif{{ $image->credit }}
                    </figcaption>
                @endif
            </figure>
        @endif

        <div @class(['lg:col-span-2 mx-auto max-w-3xl' => ! $image]) data-reveal>
            @if (filled($data['eyebrow'] ?? null))
                <p class="eyebrow">{{ $data['eyebrow'] }}</p>
            @endif
            @if (filled($data['heading'] ?? null))
                <h2 class="text-[clamp(26px,3vw,34px)] font-bold">
                    @foreach ($lines as $line)<span class="block">{{ $line }}</span>@endforeach
                </h2>
            @endif
            <x-prose :html="$data['body'] ?? null" class="mt-4 text-base text-slate-muted" />
            @if (filled($data['link_label'] ?? null) && filled($data['link_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['link_url']))
                <a href="{{ $data['link_url'] }}" class="btn btn-primary btn-sm mt-6">{{ $data['link_label'] }}</a>
            @endif
        </div>
    </div>
</section>
