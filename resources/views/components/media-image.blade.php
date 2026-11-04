@props([
    'media' => null,
    'sizes' => '100vw',
    'width' => 1440,
    'eager' => false,
    'alt' => null,
])

{{--
    Responsive image from the media library. Renders nothing for missing or
    private media, so a deleted image never breaks a page.
--}}
@if ($media instanceof \App\Models\Media && $media->isImage() && $media->isPublic())
    <img src="{{ $media->urlForWidth($width) }}"
         @if ($media->srcset()) srcset="{{ $media->srcset() }}" sizes="{{ $sizes }}" @endif
         alt="{{ $alt ?? $media->alt_text ?? '' }}"
         @if ($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
         loading="{{ $eager ? 'eager' : 'lazy' }}"
         decoding="{{ $eager ? 'sync' : 'async' }}"
         @if ($eager) fetchpriority="high" @endif
         {{ $attributes }}>
@endif
