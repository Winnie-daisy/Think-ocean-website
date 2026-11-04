<x-layout title="{{ $query ? 'Search results for “'.$query.'”' : 'Search' }}" noindex>
    <x-page-header title="Search" intro="Find pages, projects and articles across the site.">
        <form method="GET" action="{{ route('search') }}" role="search" class="mt-10 flex max-w-2xl flex-col gap-3 sm:flex-row">
            <label for="search-input" class="sr-only">Search the website</label>
            <input id="search-input" type="search" name="q" value="{{ $query }}" maxlength="100" placeholder="Search…"
                   class="form-input min-h-14 flex-1 rounded-full border-white/20 px-6 text-lg">
            <button type="submit" class="btn btn-light min-h-14 px-8">
                <x-ocean-icon name="magnifying-glass" class="size-5" /> Search
            </button>
        </form>
    </x-page-header>

    <section class="container-site pb-24 pt-6 sm:pb-32" aria-live="polite">
        @if (mb_strlen($query) >= 2)
            <h2 class="text-2xl">
                {{ $results->count() }} {{ \Illuminate\Support\Str::plural('result', $results->count()) }} for “{{ $query }}”
            </h2>

            @if ($results->isNotEmpty())
                <ol class="mt-10 divide-y divide-navy/10 border-y border-navy/10" role="list">
                    @foreach ($results as $result)
                        <li class="group relative py-7">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-deep">
                                {{ $result['type'] }}
                                @if ($result['date'])
                                    <span class="font-normal normal-case tracking-normal text-slate-muted">· {{ $result['date']->format('j F Y') }}</span>
                                @endif
                            </p>
                            <h3 class="mt-2 text-2xl">
                                <a href="{{ $result['url'] }}" class="decoration-teal decoration-2 underline-offset-4 after:absolute after:inset-0 group-hover:underline">{{ $result['title'] }}</a>
                            </h3>
                            @if ($result['summary'])
                                <p class="mt-2 max-w-3xl leading-relaxed text-slate-muted">{{ \Illuminate\Support\Str::limit($result['summary'], 220) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @else
                <x-empty-state title="No results found" icon="magnifying-glass" class="mt-10">
                    Try a different or more general word, or browse our
                    <a href="{{ route('projects.index') }}" class="link-arrow">projects</a> and
                    <a href="{{ route('news.index') }}" class="link-arrow">news</a>.
                </x-empty-state>
            @endif
        @elseif ($query !== '')
            <p class="text-slate-muted">Please enter at least two characters.</p>
        @endif
    </section>
</x-layout>
