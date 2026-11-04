@props(['sections' => []])

{{--
    Renders CMS page sections. Only known block types are rendered (unknown
    or malformed blocks are skipped). Images referenced by any section are
    loaded in a single query.
--}}
@php
    $allowed = ['hero', 'rich_text', 'feature_grid', 'image_text', 'facts', 'call_to_action', 'featured_projects', 'latest_news', 'partners', 'quote', 'team', 'image', 'enquiry_form', 'donation_form'];
    $sections = array_values(array_filter((array) $sections, fn ($s) => is_array($s) && in_array($s['type'] ?? null, $allowed, true)));

    $ids = [];
    array_walk_recursive($sections, function ($value, $key) use (&$ids) {
        if ($key === 'image_id' && is_numeric($value)) {
            $ids[] = (int) $value;
        }
    });

    $media = $ids
        ? \App\Models\Media::query()->publiclyVisible()->whereIn('id', array_unique($ids))->get()->keyBy('id')
        : collect();
@endphp

@foreach ($sections as $index => $section)
    @include('sections.'.$section['type'], [
        'data' => is_array($section['data'] ?? null) ? $section['data'] : [],
        'index' => $index,
        'media' => $media,
    ])
@endforeach
