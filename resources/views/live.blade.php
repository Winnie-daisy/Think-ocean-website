@php
    /** @var array{type: string, src: string}|null $stream */
@endphp

<x-layout :title="$page?->meta_title ?: 'Watch'" :description="$page?->meta_description ?: ($description ?: 'Video, broadcasts and stories from Think Ocean.')" :image="$page?->ogImage">
    <x-page-header title="Watch Think Ocean" :intro="$page?->intro ?: 'Video, broadcasts and stories from our work.'" fallback="news-fish" />

    <section class="section" aria-labelledby="tv-title">
        <div class="container-site">
            <div class="overflow-hidden rounded-[18px] bg-navy-ink shadow-[0_30px_70px_rgb(6_38_58/0.35)]">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 sm:px-7">
                    <h2 id="tv-title" class="text-base font-bold text-white sm:text-lg">{{ $title }}</h2>
                    @if ($stream)
                        <span class="inline-flex items-center gap-2 rounded-full bg-[#e11d48] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-white">
                            <span class="size-2 rounded-full bg-white motion-safe:animate-[on-air-pulse_1.4s_ease-in-out_infinite]"></span> Live now
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-[#cfe6ee]">Off air</span>
                    @endif
                </div>

                <div class="relative aspect-video bg-black">
                    @if ($stream && $stream['type'] === 'iframe')
                        <iframe src="{{ $stream['src'] }}" title="{{ $title }} live stream" class="absolute inset-0 size-full border-0"
                                allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    @elseif ($stream)
                        <span class="pointer-events-none absolute left-3 top-3 z-10 rounded bg-[#e00000] px-2.5 py-1 text-xs font-bold tracking-[0.08em] text-white" aria-hidden="true">● LIVE</span>
                        <video data-live-player controls autoplay muted playsinline preload="auto"
                               class="absolute inset-0 size-full bg-black object-contain"
                               @if ($stream['type'] === 'video') src="{{ $stream['src'] }}" @endif
                               data-hls="{{ $stream['type'] === 'hls' ? $stream['src'] : '' }}"
                               data-live-text="LIVE - {{ $title }}"></video>
                        <div id="stream-fallback" hidden role="alert" class="absolute inset-0 z-20 grid place-items-center bg-[linear-gradient(145deg,rgb(8_47_71/0.96),rgb(10_75_101/0.94))] p-7 text-center">
                            <div>
                                <h3 class="text-[clamp(20px,3vw,28px)] text-white">Live stream temporarily unavailable</h3>
                                <p class="mx-auto mb-5 mt-2.5 max-w-[520px] text-[#cfe6ee]">The broadcast server is responding too slowly. Please retry in a moment.</p>
                                <button type="button" id="retry-stream" class="btn btn-primary">Retry live stream</button>
                            </div>
                        </div>
                    @else
                        <div class="absolute inset-0 grid place-items-center bg-[radial-gradient(ellipse_at_center,#123f5c_0%,#0a2c42_70%)] p-6 text-center">
                            <div>
                                <span class="mx-auto grid size-16 place-items-center rounded-full border-2 border-white/80 text-white sm:size-20">
                                    <x-ocean-icon name="play" class="ml-1 size-8 sm:size-10" />
                                </span>
                                <p class="mt-4 font-display text-xl font-semibold text-white sm:text-2xl">{{ $title }}</p>
                                <p class="mt-1 text-sm text-[#cfe6ee]">{{ $schedule ?: 'Watch our latest content.' }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                @if ($stream && $stream['type'] !== 'iframe')
                    <p id="stream-status" class="px-4 py-2.5 text-center text-[13px] text-[#9fc3cf]" role="status" aria-live="polite">Connecting to live stream...</p>
                @endif
            </div>

            @if ($description)
                <p class="mx-auto mt-6 max-w-2xl text-center text-slate-muted">{{ $description }}</p>
            @endif
        </div>
    </section>

    @if ($page?->sections)
        <x-sections :sections="$page->sections" />
    @endif

    @if ($videos->isNotEmpty() || $browseUrl)
        <section class="section pt-0 sm:pt-0" aria-labelledby="videos-heading">
            <div class="container-site">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 id="videos-heading" class="section-title">A closer look at the ocean</h2>
                        <p class="mt-2 text-slate-muted">Explore video content, interviews and stories.</p>
                    </div>
                    @if ($browseUrl)
                        <a href="{{ $browseUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm shrink-0">Browse Videos</a>
                    @endif
                </div>

                @if ($videos->isNotEmpty())
                    <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" role="list">
                        @foreach ($videos as $video)
                            <li>
                                <a href="{{ $video['url'] }}" target="_blank" rel="noopener noreferrer" class="group block">
                                    <span class="relative block aspect-video overflow-hidden rounded-[14px] bg-navy">
                                        @if ($video['image'])
                                            <x-media-image :media="$video['image']" :width="960" sizes="(min-width: 1024px) 33vw, 100vw" class="size-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="" />
                                        @endif
                                        <span class="absolute inset-0 grid place-items-center bg-navy-ink/25 transition group-hover:bg-navy-ink/10">
                                            <span class="grid size-12 place-items-center rounded-full bg-white/90 text-navy"><x-ocean-icon name="play" class="ml-0.5 size-6" /></span>
                                        </span>
                                    </span>
                                    <span class="mt-3 block font-display text-[15px] font-semibold text-navy group-hover:text-teal-deep">{{ $video['title'] }}</span>
                                    <span class="sr-only">(opens in a new tab)</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif
</x-layout>
