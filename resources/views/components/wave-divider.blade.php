@props(['flip' => false])

<svg {{ $attributes->class(['pointer-events-none block h-12 w-full sm:h-16', 'rotate-180' => $flip]) }}
     viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true" focusable="false">
    <path fill="currentColor" d="M0 48c120-18 240-30 360-24s240 30 360 30 240-24 360-30 240 6 360 18v38H0Z"/>
</svg>
