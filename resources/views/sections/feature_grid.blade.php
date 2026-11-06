@php
    $items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['title'] ?? null)));
    $icons = array_keys(\App\Filament\Support\PageBlocks::ICONS);
    $layout = in_array($data['layout'] ?? null, ['tiles', 'panel'], true) ? $data['layout'] : 'tiles';
    $hasImages = collect($items)->contains(fn ($item) => $media->has((int) ($item['image_id'] ?? 0)));
    $hasHeading = filled($data['eyebrow'] ?? null) || filled($data['heading'] ?? null) || filled($data['intro'] ?? null);
    $count = count($items);
    $columns = match (true) {
        $count <= 2 => 'sm:grid-cols-2',
        $count === 3 => 'sm:grid-cols-3',
        $count === 5 => 'sm:grid-cols-3 lg:grid-cols-5',
        $count === 10 => 'grid-cols-2 lg:grid-cols-5',
        default => 'grid-cols-2 lg:grid-cols-4',
    };
@endphp

@if ($items)
    <section class="section">
        <div class="container-site">
            <x-section-heading :eyebrow="$data['eyebrow'] ?? null" :heading="$data['heading'] ?? null" :intro="$data['intro'] ?? null" :center="! $hasImages && $layout === 'tiles' && filled($data['heading'] ?? null)" data-reveal />

            @if ($layout === 'panel')
                {{-- One light panel, columns divided by fine lines (e.g. Mission / Vision / Values). --}}
                <ul @class(['tile grid divide-y divide-[#cde6ee] px-4 sm:divide-x sm:divide-y-0', 'sm:grid-cols-2' => $count === 2, 'sm:grid-cols-3' => $count === 3, 'sm:grid-cols-2 lg:grid-cols-4' => $count >= 4, 'mt-8' => $hasHeading]) role="list" data-reveal>
                    @foreach ($items as $item)
                        <li class="flex flex-col items-center px-5 py-7 text-center">
                            @if (in_array($item['icon'] ?? null, $icons, true))
                                <x-ocean-icon :name="$item['icon']" class="mb-3 size-9 text-teal" />
                            @endif
                            <h3 class="text-[15px] font-bold">{{ $item['title'] }}</h3>
                            @if (filled($item['text'] ?? null))
                                <p class="mt-1.5 max-w-[240px] text-[13px] text-slate-muted">{{ $item['text'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <ul @class(['grid gap-4 sm:gap-5', $columns, 'mt-8' => $hasHeading]) role="list">
                    @foreach ($items as $item)
                        @php
                            $image = $media->get((int) ($item['image_id'] ?? 0));
                            $link = filled($item['link_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($item['link_url']) ? $item['link_url'] : null;
                        @endphp
                        <li @class(['tile group relative flex flex-col overflow-hidden transition duration-300 hover:-translate-y-[3px] hover:shadow-card', 'px-4 py-7 text-center' => ! $image]) data-reveal style="transition-delay: {{ min($loop->index, 5) * 70 }}ms">
                            @if ($image)
                                <div class="aspect-[4/3] overflow-hidden bg-mist">
                                    <x-media-image :media="$image" sizes="(min-width: 1024px) 25vw, (min-width: 640px) 45vw, 100vw" :width="960" class="size-full object-cover transition duration-500 group-hover:scale-[1.04]" alt="" />
                                </div>
                            @endif
                            <div @class(['flex flex-1 flex-col items-center text-center', 'px-4 py-4' => $image])>
                                @if (! $image && in_array($item['icon'] ?? null, $icons, true))
                                    <x-ocean-icon :name="$item['icon']" class="mb-3 size-10 text-teal" />
                                @endif
                                <h3 @class(['font-bold', 'text-[15px] text-teal-deep' => $image, 'text-[15px]' => ! $image])>
                                    @if ($link)
                                        <a href="{{ $link }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $item['title'] }}</a>
                                    @else
                                        {{ $item['title'] }}
                                    @endif
                                </h3>
                                @if (filled($item['text'] ?? null))
                                    <p class="mt-1.5 text-[13px] text-slate-muted">{{ $item['text'] }}</p>
                                @endif
                                @if ($link && filled($item['link_label'] ?? null))
                                    <span class="link-arrow mt-auto pt-3 text-[13px]" aria-hidden="true">{{ $item['link_label'] }} <x-ocean-icon name="arrow-right" class="size-3.5" /></span>
                                @endif
                            </div>
                            @if ($link)
                                <span class="pointer-events-none absolute inset-0 rounded-[14px] ring-teal group-has-[a:focus-visible]:ring-4"></span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
