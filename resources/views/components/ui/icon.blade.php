@props([
    'name',
    'class' => 'h-5 w-5',
])

@switch($name)
    @case('dashboard')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="4" rx="1.5" />
            <rect x="14" y="10" width="7" height="11" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
        </svg>
        @break

    @case('wrench-screwdriver')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.7 6.3a4 4 0 0 0 5 5l-8.1 8.1a2 2 0 1 1-2.8-2.8l8.1-8.1a4 4 0 0 0-5-5l2.2 2.2-2.8 2.8-2.6-2.2a4 4 0 0 0 5 5" />
            <path d="m5 21-1-1" />
        </svg>
        @break

    @case('wrench')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.2 5.1a4.8 4.8 0 0 0 4.7 6l-7.8 7.8a2.6 2.6 0 1 1-3.7-3.7l7.8-7.8a4.8 4.8 0 0 0-6-4.7l3 3-2.3 2.3-3-2.9a4.8 4.8 0 0 0 4.7 6" />
            <circle cx="6.6" cy="17.4" r=".8" fill="currentColor" stroke="none" />
        </svg>
        @break

    @case('calendar')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M3 10h18" />
        </svg>
        @break

    @case('chart-bar')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 19V5" />
            <path d="M10 19V9" />
            <path d="M16 19v-6" />
            <path d="M22 19V3" />
        </svg>
        @break

    @case('chart-line-down')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 20h18" />
            <path d="m4 8 5 4 4-3 7 6" />
            <path d="M20 15v3h-3" />
        </svg>
        @break

    @case('magnifying-glass')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
        </svg>
        @break

    @case('map-pin')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 21s6-4.8 6-11a6 6 0 1 0-12 0c0 6.2 6 11 6 11Z" />
            <circle cx="12" cy="10" r="2.4" />
        </svg>
        @break

    @case('server')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="4" width="18" height="6" rx="1.5" />
            <rect x="3" y="14" width="18" height="6" rx="1.5" />
            <path d="M7 7h.01" />
            <path d="M7 17h.01" />
            <path d="M11 7h6" />
            <path d="M11 17h6" />
        </svg>
        @break

    @case('clipboard-check')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="5" y="4" width="14" height="17" rx="2" />
            <path d="M9 4.5h6v3H9z" />
            <path d="m9.5 13 2 2 4-4" />
        </svg>
        @break

    @case('file-search')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
            <path d="M14 3v5h5" />
            <circle cx="11" cy="14" r="2.5" />
            <path d="m13 16 2 2" />
        </svg>
        @break

    @case('tools-crossed')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m4 20 7-7" />
            <path d="m14 10 6-6" />
            <path d="m13 11 1 1" />
            <path d="m3 7 4-4 6 6-4 4z" />
            <path d="m11 13 6 6" />
            <path d="m17 19 3-3" />
        </svg>
        @break

    @case('pencil-square')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L8 18l-4 1 1-4 11.5-11.5Z" />
            <path d="M13.5 6.5 17.5 10.5" />
        </svg>
        @break

    @case('trash')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 6h18" />
            <path d="M8 6V4h8v2" />
            <path d="m19 6-1 14H6L5 6" />
            <path d="M10 11v6" />
            <path d="M14 11v6" />
        </svg>
        @break

    @case('power')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 2v8" />
            <path d="M7.8 4.8a8 8 0 1 0 8.4 0" />
        </svg>
        @break

    @case('play')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m8 6 10 6-10 6V6Z" />
        </svg>
        @break

    @case('eye')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z" />
            <circle cx="12" cy="12" r="3.2" />
        </svg>
        @break

    @case('qr-code')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="6" height="6" rx="1" />
            <rect x="15" y="3" width="6" height="6" rx="1" />
            <rect x="3" y="15" width="6" height="6" rx="1" />
            <path d="M15 15h2v2" />
            <path d="M19 15v6h-6" />
            <path d="M13 11h2v2" />
            <path d="M17 11h2v2" />
            <path d="M11 13h2v2" />
        </svg>
        @break

    @case('arrow-down-tray')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4v10" />
            <path d="m8 10 4 4 4-4" />
            <path d="M4 18v2h16v-2" />
        </svg>
        @break

    @case('exclamation-triangle')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4 3.5 19h17L12 4Z" />
            <path d="M12 9v4" />
            <circle cx="12" cy="16.5" r=".6" fill="currentColor" stroke="none" />
        </svg>
        @break

    @case('support')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M9.1 9a3 3 0 1 1 5.8 1c-.4 1.3-1.5 1.9-2.3 2.5-.6.4-1 .8-1 1.5" />
            <circle cx="12" cy="17" r=".5" fill="currentColor" stroke="none" />
        </svg>
        @break

    @case('settings')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3v3" />
            <path d="M12 18v3" />
            <path d="m4.9 4.9 2.1 2.1" />
            <path d="m17 17 2.1 2.1" />
            <path d="M3 12h3" />
            <path d="M18 12h3" />
            <path d="m4.9 19.1 2.1-2.1" />
            <path d="M17 7l2.1-2.1" />
            <circle cx="12" cy="12" r="4" />
        </svg>
        @break

    @case('bell')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5" />
            <path d="M9.5 17a2.5 2.5 0 0 0 5 0" />
        </svg>
        @break

    @case('chevron-down')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m5 7 5 5 5-5" />
        </svg>
        @break

    @case('arrow-right')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 10h12" />
            <path d="m10 6 4 4-4 4" />
        </svg>
        @break

    @case('clock')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 3" />
        </svg>
        @break

    @case('shield-check')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3 5 6v5c0 5 3.5 8.7 7 10 3.5-1.3 7-5 7-10V6l-7-3Z" />
            <path d="m9.5 12 1.7 1.7 3.3-3.7" />
        </svg>
        @break

    @case('user-circle')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="8" r="3.5" />
            <path d="M5 20a7 7 0 0 1 14 0" />
        </svg>
        @break

    @case('bars-3')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 7h16" />
            <path d="M4 12h16" />
            <path d="M4 17h16" />
        </svg>
        @break

    @case('x-mark')
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 6 12 12" />
            <path d="M18 6 6 18" />
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8h.01" />
            <path d="M11 12h1v4h1" />
        </svg>
@endswitch
