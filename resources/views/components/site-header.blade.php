@php
    $onAir = (bool) $site->get('live_enabled');
    $watchHref = route('watch', absolute: false);
    $links = collect([['label' => 'Home', 'href' => route('home'), 'current' => request()->routeIs('home'), 'new_tab' => false]])
        ->concat($navigation->for('header'))
        ->map(fn (array $link) => [...$link, 'on_air' => $onAir && rtrim((string) parse_url($link['href'], PHP_URL_PATH), '/') === $watchHref]);
    $partnerUrl = route('contact', ['subject' => 'Partnership']);
@endphp

<header class="sticky top-0 z-40 w-full border-b border-line bg-white/95 backdrop-blur-md">
    <div class="container-site flex min-h-[72px] items-center justify-between gap-6">
        <x-brand />

        <nav aria-label="Main" class="hidden xl:block">
            <ul class="flex items-center gap-5 text-[13.5px] font-medium">
                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link['href'] }}"
                           @if ($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                           @if ($link['current']) aria-current="page" @endif
                           @class([
                               'relative py-1.5 transition hover:text-teal-deep',
                               'text-teal-deep after:absolute after:inset-x-0 after:-bottom-0.5 after:h-0.5 after:rounded-full after:bg-teal' => $link['current'],
                               'text-navy' => ! $link['current'],
                           ])>@if ($link['on_air'])<span class="mr-1.5 inline-block size-2 rounded-full bg-[#e11d48] align-[1px] motion-safe:animate-[on-air-pulse_1.4s_ease-in-out_infinite]"></span><span class="sr-only">On air: </span>@endif{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-2">
            <a href="{{ $partnerUrl }}" class="btn btn-primary btn-sm hidden sm:inline-flex">Partner With Us</a>

            {{-- Mobile menu: a native disclosure, so it works without JavaScript. --}}
            <details class="group xl:hidden" data-mobile-nav>
                <summary class="grid size-11 cursor-pointer list-none place-items-center rounded-[10px] text-navy [&::-webkit-details-marker]:hidden" aria-label="Open menu">
                    <x-ocean-icon name="bars-3" class="size-[26px] group-open:hidden" />
                    <x-ocean-icon name="x-mark" class="hidden size-[26px] group-open:block" />
                </summary>
                <nav aria-label="Main" class="absolute inset-x-0 top-[72px] grid max-h-[calc(100vh-72px)] gap-1 overflow-y-auto border-b border-line bg-white p-4 shadow-[0_18px_30px_rgb(11_61_91/0.12)]">
                    @foreach ($links as $link)
                        <a href="{{ $link['href'] }}"
                           @if ($link['new_tab']) target="_blank" rel="noopener noreferrer" @endif
                           @if ($link['current']) aria-current="page" @endif
                           @class([
                               'rounded-lg px-2 py-3 font-medium text-navy',
                               'bg-mist text-teal-deep' => $link['current'],
                           ])>@if ($link['on_air'])<span class="mr-1.5 inline-block size-2 rounded-full bg-[#e11d48]"></span><span class="sr-only">On air: </span>@endif{{ $link['label'] }}</a>
                    @endforeach
                    <a href="{{ $partnerUrl }}" class="btn btn-primary mt-2">Partner With Us</a>
                </nav>
            </details>
        </div>
    </div>
</header>
