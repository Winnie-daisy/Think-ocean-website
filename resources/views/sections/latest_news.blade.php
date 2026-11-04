@php
    $limit = in_array((int) ($data['limit'] ?? 3), [3, 6], true) ? (int) ($data['limit'] ?? 3) : 3;
    $posts = \App\Models\Post::query()->published()->with(['featuredImage', 'categories'])->latest('published_at')->limit($limit)->get();
@endphp

<section class="section">
    <div class="container-site">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-reveal>
            <x-section-heading :eyebrow="$data['eyebrow'] ?? null" :heading="$data['heading'] ?? 'News and insights'" :intro="$data['intro'] ?? null" />
            @if ($posts->isNotEmpty())
                <a href="{{ route('news.index') }}" class="link-arrow shrink-0">All news <x-ocean-icon name="arrow-right" class="size-4" /></a>
            @endif
        </div>

        @if ($posts->isNotEmpty())
            <ul class="mt-10 grid gap-7 sm:grid-cols-2 lg:grid-cols-3" role="list">
                @foreach ($posts as $post)
                    <li class="flex" data-reveal style="transition-delay: {{ $loop->index * 80 }}ms"><x-post-card :post="$post" class="w-full" /></li>
                @endforeach
            </ul>
        @else
            <x-empty-state title="News and insights are on their way" icon="newspaper" class="mt-10">
                Our first articles will appear here once they are published.
            </x-empty-state>
        @endif
    </div>
</section>
