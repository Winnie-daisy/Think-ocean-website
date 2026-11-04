@props([
    'eyebrow' => null,
    'heading' => null,
    'intro' => null,
    'light' => false,
    'center' => false,
    'level' => 'h2',
])

@if ($eyebrow || $heading || $intro)
    <div {{ $attributes->class(['max-w-3xl', 'mx-auto text-center' => $center]) }}>
        @if ($eyebrow)
            <p @class(['eyebrow', 'eyebrow-light' => $light])>{{ $eyebrow }}</p>
        @endif
        @if ($heading)
            <{{ $level }} @class(['section-title', 'text-white' => $light])>{{ $heading }}</{{ $level }}>
        @endif
        @if ($intro)
            <p @class([
                'mt-3 text-[17px]',
                'text-white/80' => $light,
                'text-slate-muted' => ! $light,
            ])>{{ $intro }}</p>
        @endif
    </div>
@endif
