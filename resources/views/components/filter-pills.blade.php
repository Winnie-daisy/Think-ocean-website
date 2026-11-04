@props(['categories', 'active' => null, 'route', 'label' => 'Filter by category'])

@if ($categories->isNotEmpty())
    <nav aria-label="{{ $label }}" class="flex flex-wrap gap-2">
        <a href="{{ route($route) }}" @if (! $active) aria-current="page" @endif
           class="inline-flex min-h-9 items-center rounded-full border px-4 text-[13px] font-medium transition {{ $active ? 'border-line bg-white text-charcoal hover:border-teal hover:text-teal-deep' : 'border-teal-deep bg-teal-deep text-white' }}">
            All
        </a>
        @foreach ($categories as $category)
            @php($isActive = $active?->is($category))
            <a href="{{ route($route, ['category' => $category->slug]) }}" @if ($isActive) aria-current="page" @endif
               class="inline-flex min-h-9 items-center rounded-full border px-4 text-[13px] font-medium transition {{ $isActive ? 'border-teal-deep bg-teal-deep text-white' : 'border-line bg-white text-charcoal hover:border-teal hover:text-teal-deep' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </nav>
@endif
