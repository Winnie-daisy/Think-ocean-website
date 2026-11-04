{{-- Fine contour lines suggesting swell; decorative. --}}
<svg {{ $attributes->class(['pointer-events-none']) }} viewBox="0 0 1440 200" preserveAspectRatio="none" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1">
    @foreach (range(0, 7) as $i)
        <path d="M0 {{ 60 + $i * 18 }} C 240 {{ 30 + $i * 18 }}, 480 {{ 90 + $i * 18 }}, 720 {{ 60 + $i * 18 }} S 1200 {{ 30 + $i * 18 }}, 1440 {{ 60 + $i * 18 }}" opacity="{{ 1 - $i * 0.1 }}" />
    @endforeach
</svg>
