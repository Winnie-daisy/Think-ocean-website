@php($image = $media->get((int) ($data['image_id'] ?? 0)))

{{-- A wide decorative photograph between sections. --}}
@if ($image)
    <section aria-label="{{ $image->alt_text ?: 'Photograph' }}">
        <x-media-image :media="$image" sizes="100vw" :width="1920" class="h-[220px] w-full object-cover sm:h-[300px]" />
    </section>
@endif
