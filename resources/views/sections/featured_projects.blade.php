@php
    $limit = in_array((int) ($data['limit'] ?? 3), [3, 6], true) ? (int) ($data['limit'] ?? 3) : 3;
    $gallery = ($data['style'] ?? 'cards') === 'gallery';
    $projects = \App\Models\Project::query()->published()->with(['featuredImage', 'category'])
        ->orderByDesc('is_featured')->latest('published_at')->limit($limit)->get();
    $fallbacks = ['project-reef', 'project-fisheries', 'project-blue-carbon', 'project-education'];
@endphp

<section class="section">
    <div class="container-site">
        @if ($gallery)
            {{-- "Discover the work": centred heading, a button and a strip of project photographs. --}}
            <div class="text-center" data-reveal>
                <x-section-heading :eyebrow="$data['eyebrow'] ?? null" :heading="$data['heading'] ?? 'Discover the work'" :intro="$data['intro'] ?? null" center />
                @if ($projects->isNotEmpty())
                    <a href="{{ route('projects.index') }}" class="btn btn-primary btn-sm mt-5">View All Projects</a>
                @endif
            </div>

            @if ($projects->isNotEmpty())
                <ul class="mt-8 grid gap-4 sm:grid-cols-3" role="list">
                    @foreach ($projects as $project)
                        <li data-reveal style="transition-delay: {{ $loop->index * 80 }}ms">
                            <a href="{{ $project->publicUrl() }}" class="group relative block aspect-[16/10] overflow-hidden rounded-[14px] bg-mist">
                                @if ($project->featuredImage)
                                    <x-media-image :media="$project->featuredImage" sizes="(min-width: 640px) 33vw, 100vw" :width="960" class="size-full object-cover transition duration-500 group-hover:scale-[1.05]" alt="" />
                                @else
                                    <img src="{{ asset('images/think-ocean/'.$fallbacks[$project->id % count($fallbacks)].'.jpg') }}" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-[1.05]">
                                @endif
                                <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy-ink/85 to-transparent px-4 pb-3 pt-10 text-left text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ $project->title }}</span>
                                <span class="sr-only">{{ $project->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @else
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-reveal>
                <x-section-heading :eyebrow="$data['eyebrow'] ?? null" :heading="$data['heading'] ?? 'Featured projects'" :intro="$data['intro'] ?? null" />
                @if ($projects->isNotEmpty())
                    <a href="{{ route('projects.index') }}" class="link-arrow shrink-0">All projects <x-ocean-icon name="arrow-right" class="size-4" /></a>
                @endif
            </div>

            @if ($projects->isNotEmpty())
                <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach ($projects as $project)
                        <li class="flex" data-reveal style="transition-delay: {{ $loop->index * 80 }}ms"><x-project-card :project="$project" class="w-full" /></li>
                    @endforeach
                </ul>
            @endif
        @endif

        @if ($projects->isEmpty())
            <x-empty-state title="Our projects will be shared here soon" icon="globe-europe-africa" class="mt-8">
                We are preparing information about our work. Please check back shortly.
            </x-empty-state>
        @endif
    </div>
</section>
