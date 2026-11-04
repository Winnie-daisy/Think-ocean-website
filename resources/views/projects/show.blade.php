@php
    /** @var \App\Models\Project $project */
    $gallery = $project->galleryMedia();
    $details = array_filter([
        'Status' => $project->project_status?->getLabel(),
        'Category' => $project->category?->name,
        'Location' => $project->location,
        'Started' => $project->start_date?->format('F Y'),
        'Ended' => $project->end_date?->format('F Y'),
    ]);
@endphp

<x-layout
    :title="$project->meta_title ?: $project->title"
    :description="$project->meta_description ?: $project->summary"
    :image="$project->ogImage ?? $project->featuredImage"
    :canonical="$project->publicUrl()"
>
    <x-page-header :title="$project->title" :intro="$project->summary" :image="$project->featuredImage">
        <x-slot:before>
            <nav aria-label="Breadcrumb" class="mb-6 text-sm text-white/75">
                <a href="{{ route('projects.index') }}" class="inline-flex min-h-11 items-center gap-2 hover:text-white">
                    <x-ocean-icon name="arrow-left" class="size-4" /> All projects
                </a>
            </nav>
        </x-slot:before>
    </x-page-header>

    <div class="container-site grid gap-14 pb-24 pt-10 lg:grid-cols-12 lg:gap-20 lg:pb-32">
        <article class="lg:col-span-8">
            @if (filled($project->description))
                <x-prose :html="$project->description" />
            @endif

            @if ($gallery->isNotEmpty())
                <section class="mt-16" aria-labelledby="gallery-heading">
                    <h2 id="gallery-heading" class="text-3xl">Gallery</h2>
                    <ul class="mt-8 grid gap-6 sm:grid-cols-2" role="list">
                        @foreach ($gallery as $image)
                            <li>
                                <figure>
                                    <div class="aspect-[4/3] overflow-hidden rounded-2xl bg-mist">
                                        <x-media-image :media="$image" sizes="(min-width: 1024px) 30vw, (min-width: 640px) 50vw, 100vw" :width="960" class="size-full object-cover" />
                                    </div>
                                    @if ($image->caption || $image->credit)
                                        <figcaption class="mt-2 text-sm text-slate-muted">{{ $image->caption }}@if ($image->caption && $image->credit) · @endif{{ $image->credit }}</figcaption>
                                    @endif
                                </figure>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </article>

        @if ($details)
            <aside class="lg:col-span-4" aria-label="Project details">
                <div class="rounded-[14px] bg-white p-8 shadow-[0_1px_2px_rgb(6_59_86/0.06),0_12px_32px_-12px_rgb(6_59_86/0.18)] lg:sticky lg:top-8">
                    <h2 class="font-sans text-xs font-semibold uppercase tracking-[0.18em] text-teal-deep">Project details</h2>
                    <dl class="mt-6 divide-y divide-navy/10">
                        @foreach ($details as $label => $value)
                            <div class="flex justify-between gap-6 py-3">
                                <dt class="text-slate-muted">{{ $label }}</dt>
                                <dd class="text-right font-medium text-navy">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if (\App\Models\Page::slugIsLive('get-involved'))
                        <a href="{{ route('get-involved') }}" class="btn btn-primary mt-8 w-full">Get involved</a>
                    @else
                        <a href="{{ route('contact') }}" class="btn btn-primary mt-8 w-full">Get in touch</a>
                    @endif
                </div>
            </aside>
        @endif
    </div>

    @if ($related->isNotEmpty())
        <section class="bg-soft py-20 sm:py-24" aria-labelledby="related-heading">
            <div class="container-site">
                <h2 id="related-heading" class="text-3xl sm:text-4xl">More projects</h2>
                <ul class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3" role="list">
                    @foreach ($related as $item)
                        <li class="flex"><x-project-card :project="$item" class="w-full" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layout>
