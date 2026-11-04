<x-layout
    :title="$page?->meta_title ?: ($page?->title ?? 'Projects').($projects->currentPage() > 1 ? ' — page '.$projects->currentPage() : '')"
    :description="$page?->meta_description ?: $page?->intro"
    :image="$page?->ogImage"
    :canonical="$projects->currentPage() > 1 || $activeCategory ? request()->fullUrl() : route('projects.index')"
>
    <x-page-header :title="$page?->title ?? 'Projects'" :intro="$page?->intro" />

    @if ($page?->sections)
        <x-sections :sections="$page->sections" />
    @endif

    <section @class(['section', 'pt-0 sm:pt-0' => $page?->sections])>
        <div class="container-site">
            <x-filter-pills :categories="$categories" :active="$activeCategory" route="projects.index" label="Filter projects by category" />

            @if ($projects->isNotEmpty())
                <h2 class="sr-only">{{ $activeCategory ? $activeCategory->name.' projects' : 'All projects' }}</h2>
                <ul class="mt-8 grid gap-6 md:grid-cols-2" role="list">
                    @foreach ($projects as $project)
                        <li class="flex"><x-project-card :project="$project" class="w-full" /></li>
                    @endforeach
                </ul>

                <x-pagination :paginator="$projects" more="View More Projects" />
            @else
                <x-empty-state title="No projects published yet" icon="globe-europe-africa" class="mt-8">
                    We are preparing information about our work. Please check back soon, or
                    <a href="{{ route('contact') }}" class="link-arrow">get in touch</a>.
                </x-empty-state>
            @endif
        </div>
    </section>
</x-layout>
