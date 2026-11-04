<x-layout
    :title="$page?->meta_title ?: ($page?->title ?? 'News and insights').($posts->currentPage() > 1 ? ' — page '.$posts->currentPage() : '')"
    :description="$page?->meta_description ?: $page?->intro"
    :image="$page?->ogImage"
    :canonical="$posts->currentPage() > 1 || $activeCategory || $activeTag ? request()->fullUrl() : route('news.index')"
>
    <x-page-header :title="$page?->title ?? 'News and insights'" :intro="$page?->intro" />

    @if ($page?->sections)
        <x-sections :sections="$page->sections" />
    @endif

    <section @class(['section', 'pt-0 sm:pt-0' => $page?->sections])>
        <div class="container-site">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <x-filter-pills :categories="$categories" :active="$activeCategory" route="news.index" label="Filter news by category" />

                <form method="GET" action="{{ route('search') }}" role="search" class="flex w-full items-center rounded-full border border-line bg-white pl-4 focus-within:border-teal sm:w-[280px]">
                    <label for="news-search" class="sr-only">Search articles</label>
                    <input id="news-search" type="search" name="q" maxlength="100" placeholder="Search articles..." class="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-sm outline-none">
                    <button type="submit" class="grid size-[42px] place-items-center text-navy" aria-label="Search">
                        <x-ocean-icon name="magnifying-glass" class="size-[18px]" />
                    </button>
                </form>
            </div>

            @if ($activeTag)
                <p class="mt-6 text-slate-muted">
                    Showing articles tagged <strong class="text-navy">{{ $activeTag->name }}</strong>.
                    <a href="{{ route('news.index') }}" class="link-arrow ml-2">Show all</a>
                </p>
            @endif

            @if ($posts->isNotEmpty())
                <h2 class="sr-only">Articles</h2>
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach ($posts as $post)
                        <li class="flex"><x-post-card :post="$post" class="w-full" /></li>
                    @endforeach
                </ul>

                <x-pagination :paginator="$posts" more="View More News" />
            @else
                <x-empty-state title="No articles published yet" icon="newspaper" class="mt-8">
                    News and insights will appear here once they are published.
                </x-empty-state>
            @endif
        </div>
    </section>
</x-layout>
