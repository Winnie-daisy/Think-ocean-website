@php
    /** @var \App\Models\Post $post */
    $site = app(\App\Support\SiteSettings::class);
    $article = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'description' => $post->excerpt,
        'datePublished' => $post->published_at?->toIso8601String(),
        'dateModified' => $post->updated_at?->toIso8601String(),
        'image' => $post->featuredImage?->urlForWidth(1200),
        'mainEntityOfPage' => $post->publicUrl(),
        'publisher' => ['@type' => 'Organization', 'name' => $site->get('organisation_name')],
    ]);
@endphp

<x-layout
    :title="$post->meta_title ?: $post->title"
    :description="$post->meta_description ?: $post->excerpt"
    :image="$post->ogImage ?? $post->featuredImage"
    :canonical="$post->publicUrl()"
    type="article"
    :json-ld="$article"
>
    <article>
        <header class="container-site pt-12 sm:pt-16">
            <div class="mx-auto max-w-3xl">
                <nav aria-label="Breadcrumb" class="text-sm text-slate-muted">
                    <a href="{{ route('news.index') }}" class="inline-flex min-h-11 items-center gap-2 hover:text-navy">
                        <x-ocean-icon name="arrow-left" class="size-4" /> News and insights
                    </a>
                </nav>

                @if ($post->categories->isNotEmpty())
                    <p class="eyebrow mt-6">{{ $post->categories->pluck('name')->implode(' · ') }}</p>
                @endif

                <h1 class="mt-5 text-4xl leading-[1.08] sm:text-5xl lg:text-6xl">{{ $post->title }}</h1>

                @if ($post->excerpt)
                    <p class="mt-6 text-xl leading-relaxed text-slate-muted">{{ $post->excerpt }}</p>
                @endif

                <p class="mt-8 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-navy/10 pt-6 text-sm text-slate-muted">
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j F Y') }}</time>
                    @endif
                    @if ($post->author)
                        <span aria-hidden="true">·</span>
                        <span>By {{ $post->author->name }}</span>
                    @endif
                </p>
            </div>
        </header>

        @if ($post->featuredImage)
            <figure class="container-site mt-12">
                <div class="mx-auto max-w-5xl overflow-hidden rounded-[14px] bg-mist">
                    <x-media-image :media="$post->featuredImage" eager sizes="(min-width: 1280px) 1024px, 100vw" :width="1440" class="aspect-[16/9] w-full object-cover" />
                </div>
                @if ($post->featuredImage->caption || $post->featuredImage->credit)
                    <figcaption class="mx-auto mt-3 max-w-5xl text-sm text-slate-muted">
                        {{ $post->featuredImage->caption }}@if ($post->featuredImage->caption && $post->featuredImage->credit) · @endif{{ $post->featuredImage->credit }}
                    </figcaption>
                @endif
            </figure>
        @endif

        <div class="container-site py-14 sm:py-20">
            <x-prose :html="$post->body" class="mx-auto max-w-3xl" />

            @if ($post->tags->isNotEmpty())
                <div class="mx-auto mt-14 max-w-3xl border-t border-navy/10 pt-8">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-[0.18em] text-slate-muted">Tags</h2>
                    <ul class="mt-4 flex flex-wrap gap-2" role="list">
                        @foreach ($post->tags as $tag)
                            <li><a href="{{ route('news.index', ['tag' => $tag->slug]) }}" class="inline-flex min-h-11 items-center rounded-full bg-mist px-4 text-sm font-medium text-navy hover:bg-teal-deep hover:text-white">{{ $tag->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="bg-white py-20 sm:py-24" aria-labelledby="related-heading">
            <div class="container-site">
                <h2 id="related-heading" class="text-3xl sm:text-4xl">More news and insights</h2>
                <ul class="mt-12 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach ($related as $item)
                        <li class="flex"><x-post-card :post="$item" class="w-full" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layout>
