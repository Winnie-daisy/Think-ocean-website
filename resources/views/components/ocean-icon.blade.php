@props(['name'])

{{-- Heroicons (outline), decorative by default. "globe" is a CMS icon choice without a Heroicon of that exact name. --}}
@php($name = ['globe' => 'globe-europe-africa'][$name] ?? $name)
@svg('heroicon-o-'.$name, $attributes->get('class', 'size-6'), ['aria-hidden' => 'true', 'focusable' => 'false'])
