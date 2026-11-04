@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['name'] ?? null)));
@endphp

@if ($items)
    <section class="section">
        <div class="container-site">
            <x-section-heading :heading="$data['heading'] ?? 'Our Partners'" :intro="$data['intro'] ?? null" center class="max-w-[620px]" data-reveal />

            <ul class="mt-9 flex flex-wrap justify-center gap-5" role="list">
                @foreach ($items as $item)
                    @php
                        $logo = $media->get((int) ($item['image_id'] ?? 0));
                        $url = filled($item['url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($item['url']) ? $item['url'] : null;
                        $tag = $url ? 'a' : 'div';
                    @endphp
                    <li class="w-[calc(50%-10px)] sm:w-[200px]" data-reveal>
                        <{{ $tag }} @if ($url) href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="Visit {{ $item['name'] }}" @endif
                            @class([
                                'group flex h-full min-h-[110px] flex-col items-center justify-center gap-2.5 rounded-[14px] border border-line bg-white p-3.5 text-center transition duration-300 sm:min-h-[130px] sm:p-5',
                                'hover:-translate-y-[3px] hover:border-[#bfe3e6] hover:shadow-card' => $url,
                            ])>
                            @if ($logo)
                                <x-media-image :media="$logo" :width="480" sizes="150px" alt="{{ $item['name'] }} logo"
                                               class="max-h-[52px] max-w-[120px] object-contain opacity-85 grayscale transition duration-300 group-hover:opacity-100 group-hover:grayscale-0 group-focus-visible:grayscale-0 sm:max-h-16 sm:max-w-[150px]" />
                                <span class="text-xs leading-tight text-slate-muted">{{ $item['name'] }}</span>
                            @else
                                <span class="font-display text-sm font-semibold leading-tight text-navy">{{ $item['name'] }}</span>
                            @endif
                            @if ($url)<span class="sr-only">(opens in a new tab)</span>@endif
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ul>

            @if (filled($data['link_label'] ?? null) && filled($data['link_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['link_url']))
                <p class="mt-8 text-center">
                    <a href="{{ $data['link_url'] }}" class="link-arrow">{{ $data['link_label'] }} <x-ocean-icon name="arrow-right" class="size-4" /></a>
                </p>
            @endif
        </div>
    </section>
@endif
