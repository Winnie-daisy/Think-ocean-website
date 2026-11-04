@props(['html' => null, 'dark' => false])

{{-- CMS rich text, sanitised before output. --}}
@php($clean = app(\App\Support\HtmlSanitizer::class)->clean($html))

@if ($clean !== '')
    <div {{ $attributes->class(['prose-ocean', 'prose-on-dark' => $dark]) }}>{!! $clean !!}</div>
@endif
