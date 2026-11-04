@php($image = $media->get((int) ($data['image_id'] ?? 0)))

@if (filled($data['text'] ?? null))
    <section class="relative isolate bg-navy py-[110px] text-center text-white">
        @if ($image)
            <x-media-image :media="$image" sizes="100vw" :width="1920" alt="" class="absolute inset-0 -z-20 size-full object-cover" />
        @else
            <img src="{{ asset('images/think-ocean/about-quote.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-20 size-full object-cover">
        @endif
        <div class="absolute inset-0 -z-10 bg-navy-deep/55"></div>

        <div class="container-site" data-reveal>
            <blockquote>
                <p class="mx-auto max-w-[620px] font-display text-[clamp(24px,3vw,34px)] font-medium leading-[1.35]">“{{ trim($data['text'], '“”" ') }}”</p>
                @if (filled($data['attribution'] ?? null))
                    <footer class="mt-4 text-[#cfe6ee]">— {{ $data['attribution'] }}</footer>
                @endif
            </blockquote>
        </div>
    </section>
@endif
