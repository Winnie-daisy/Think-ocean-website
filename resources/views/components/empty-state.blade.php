@props(['title', 'icon' => 'sparkles'])

<div {{ $attributes->class(['rounded-[14px] border border-dashed border-[#a9cbd6] bg-white px-6 py-10 text-center']) }}>
    <span class="mx-auto inline-flex size-14 items-center justify-center rounded-full bg-mist text-teal-deep">
        <x-ocean-icon :name="$icon" class="size-7" />
    </span>
    <p class="mt-4 font-display text-xl font-semibold text-navy">{{ $title }}</p>
    @if (trim($slot))
        <div class="mx-auto mt-2 max-w-md text-slate-muted">{{ $slot }}</div>
    @endif
</div>
