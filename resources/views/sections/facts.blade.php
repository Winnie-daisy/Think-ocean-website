@php($items = array_values(array_filter((array) ($data['items'] ?? []), fn ($item) => is_array($item) && filled($item['value'] ?? null) && filled($item['label'] ?? null))))

@if ($items)
    <section class="bg-mist py-14">
        <div class="container-site">
            <x-section-heading :heading="$data['heading'] ?? null" data-reveal />
            <dl @class(['grid gap-6 sm:grid-cols-2 lg:grid-cols-3', 'mt-8' => filled($data['heading'] ?? null)])>
                @foreach ($items as $item)
                    <div class="flex flex-col px-5 py-3 text-center sm:[&:not(:first-child)]:border-l sm:[&:not(:first-child)]:border-[#c6e3ea]" data-reveal>
                        <dt class="order-2 mt-1 text-sm text-slate-muted">{{ $item['label'] }}</dt>
                        <dd class="order-1 font-display text-4xl font-semibold text-navy">{{ $item['value'] }}</dd>
                        @if (filled($item['source'] ?? null))
                            <dd class="order-3 mt-2 text-xs text-slate-muted">Source: {{ $item['source'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endif
