@php
    $members = array_values(array_filter((array) ($data['members'] ?? []), fn ($member) => is_array($member) && filled($member['name'] ?? null)));
@endphp

{{-- Shown only once real team members have been added in the CMS. --}}
@if ($members)
    <section class="section">
        <div class="container-site">
            <div class="tile px-6 py-10 text-center sm:px-10" data-reveal>
                <x-section-heading :heading="$data['heading'] ?? 'Meet the people behind Think Ocean'" :intro="$data['intro'] ?? null" center />

                <ul class="mt-8 flex flex-wrap justify-center gap-x-10 gap-y-8" role="list">
                    @foreach ($members as $member)
                        @php($photo = $media->get((int) ($member['image_id'] ?? 0)))
                        <li class="w-[150px]">
                            <div class="mx-auto size-28 overflow-hidden rounded-full border-4 border-white bg-white shadow-card">
                                @if ($photo)
                                    <x-media-image :media="$photo" :width="480" sizes="112px" class="size-full object-cover" alt="" />
                                @else
                                    <span class="grid size-full place-items-center font-display text-2xl font-semibold text-teal-deep" aria-hidden="true">{{ \Illuminate\Support\Str::of($member['name'])->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
                                @endif
                            </div>
                            <p class="mt-3 font-display text-sm font-semibold text-navy">{{ $member['name'] }}</p>
                            @if (filled($member['role'] ?? null))
                                <p class="text-[13px] text-slate-muted">{{ $member['role'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if (filled($data['link_label'] ?? null) && filled($data['link_url'] ?? null) && \App\Models\NavigationItem::isSafeUrl($data['link_url']))
                    <a href="{{ $data['link_url'] }}" class="btn btn-primary btn-sm mt-8">{{ $data['link_label'] }}</a>
                @endif
            </div>
        </div>
    </section>
@endif
