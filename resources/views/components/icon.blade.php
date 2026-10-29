@props(['name', 'strokeWidth' => '1.75'])

{{-- Reusable inline icon set (hand-drawn, heroicons-style). No icon package dependency. --}}

<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    {{ $attributes->merge(['class' => 'h-5 w-5']) }}
    aria-hidden="true"
>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7.5" height="7.5" rx="1.5" />
            <rect x="13.5" y="3" width="7" height="7.5" rx="1.5" />
            <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5" />
            <rect x="13.5" y="13.5" width="7" height="7.5" rx="1.5" />
            @break

        @case('users')
            <circle cx="9" cy="8" r="3" />
            <path d="M3.5 20c0-3 2.5-5.5 5.5-5.5s5.5 2.5 5.5 5.5" />
            <circle cx="17.2" cy="8.7" r="2.3" />
            <path d="M15.7 14.7c2.5.5 4.3 2.6 4.3 5.3" />
            @break

        @case('gift')
            <rect x="4" y="9" width="16" height="4" rx="1" />
            <path d="M6 13v6.5a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V13" />
            <path d="M12 9v11.5" />
            <path d="M12 9c-1.1-2.8-4.2-3.8-5.3-2S8 9.7 9 8.9c1-.8 2.1-.2 3 .1Z" />
            <path d="M12 9c1.1-2.8 4.2-3.8 5.3-2S16 9.7 15 8.9c-1-.8-2.1-.2-3 .1Z" />
            @break

        @case('banknotes')
            <rect x="2.5" y="6" width="19" height="12" rx="2.2" />
            <circle cx="12" cy="12" r="2.75" />
            <path d="M6 9v.01" />
            <path d="M18 15v.01" />
            @break

        @case('archive')
            <rect x="3" y="4" width="18" height="4.5" rx="1.2" />
            <path d="M4.5 8.5v9.5a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8.5" />
            <path d="M10 13h4" />
            @break

        @case('scale')
            <path d="M12 3 3 8h18l-9-5Z" />
            <path d="M5 8v10" />
            <path d="M19 8v10" />
            <path d="M9 12v6" />
            <path d="M15 12v6" />
            <path d="M3.5 21h17" />
            @break

        @case('chart-document')
            <path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M15 3v4h4" />
            <path d="M9 17v-3" />
            <path d="M12 17v-5" />
            <path d="M15 17v-2" />
            @break

        @case('document')
            <path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
            <path d="M15 3v4h4" />
            <path d="M8.5 13h7" />
            <path d="M8.5 16.5h7" />
            @break

        @case('shield-check')
            <path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z" />
            <path d="m9.2 12 1.9 1.9 3.7-3.9" />
            @break

        @case('logout')
            <path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4" />
            <path d="M15 16.5 20 12l-5-4.5" />
            <path d="M20 12H9" />
            @break

        @case('refresh')
            <path d="M4 9.5A8 8 0 0 1 18.5 6.5L20 8" />
            <path d="M20 4v4h-4" />
            <path d="M20 14.5A8 8 0 0 1 5.5 17.5L4 16" />
            <path d="M4 20v-4h4" />
            @break

        @case('wallet')
            <rect x="3" y="6" width="18" height="13" rx="2.2" />
            <path d="M3 10h18" />
            <path d="M16.5 14.2h2" />
            @break

        @case('clock')
            <circle cx="12" cy="12" r="8.25" />
            <path d="M12 7.5V12l3 2" />
            @break

        @case('cube')
            <path d="M12 3.5 20 8v8l-8 4.5L4 16V8l8-4.5Z" />
            <path d="M4 8l8 4.5L20 8" />
            <path d="M12 12.5V21" />
            @break

        @case('alert-triangle')
            <path d="M10.3 4.2 2.9 17.5A1.5 1.5 0 0 0 4.2 19.8h15.6a1.5 1.5 0 0 0 1.3-2.3L13.7 4.2a1.5 1.5 0 0 0-2.6 0Z" />
            <path d="M12 9.5v4" />
            <path d="M12 16.2v.01" />
            @break

        @case('megaphone')
            <path d="M3 10v4a1 1 0 0 0 1 1h2l4.5 3.5V5.5L6 9H4a1 1 0 0 0-1 1Z" />
            <path d="M14.5 8a4 4 0 0 1 0 8" />
            <path d="M17.5 5.5a8 8 0 0 1 0 13" />
            @break

        @case('search')
            <circle cx="10.5" cy="10.5" r="6" />
            <path d="m20 20-4.8-4.8" />
            @break

        @case('terminal')
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="m7 9 3 3-3 3" />
            <path d="M13 15h4" />
            @break

        @case('clipboard-list')
            <rect x="6" y="4.5" width="12" height="16" rx="1.6" />
            <rect x="9" y="3" width="6" height="3" rx="1" />
            <path d="M9 11h6" />
            <path d="M9 14.5h6" />
            <path d="M9 18h3.5" />
            @break

        @case('heart')
            <path d="M12 20s-7-4.35-9.5-9A5 5 0 0 1 12 6a5 5 0 0 1 9.5 5c-2.5 4.65-9.5 9-9.5 9Z" />
            @break

        @case('inbox')
            <path d="M4 12h4l1.5 3h5L16 12h4" />
            <path d="M4 12 5.5 5A1.5 1.5 0 0 1 7 4h10a1.5 1.5 0 0 1 1.5 1L20 12" />
            <path d="M4 12v6a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-6" />
            @break

        @case('alert-circle')
            <circle cx="12" cy="12" r="8.25" />
            <path d="M12 8v5" />
            <path d="M12 15.5v.01" />
            @break

        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break

        @case('chevron-right')
            <path d="m9 6 6 6-6 6" />
            @break

        @case('check-circle')
            <circle cx="12" cy="12" r="8.25" />
            <path d="m8.5 12.3 2.4 2.4 4.6-4.9" />
            @break

        @case('x-circle')
            <circle cx="12" cy="12" r="8.25" />
            <path d="m9.5 9.5 5 5" />
            <path d="m14.5 9.5-5 5" />
            @break

        @case('plus')
            <path d="M12 5v14" />
            <path d="M5 12h14" />
            @break

        @case('upload')
            <path d="M12 16V4" />
            <path d="m7 9 5-5 5 5" />
            <path d="M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3" />
            @break

        @case('pencil')
            <path d="M15.2 4.3 19.7 8.8" />
            <path d="M5 19l.9-3.8L15.5 5.6a1.8 1.8 0 0 1 2.5 0l1.4 1.4a1.8 1.8 0 0 1 0 2.5L9.8 19.1 5 19Z" />
            @break

        @case('trash')
            <path d="M4.5 7h15" />
            <path d="M9 7V4.8c0-.4.3-.8.8-.8h4.4c.5 0 .8.4.8.8V7" />
            <path d="M6.5 7l.7 12c0 .8.7 1.5 1.5 1.5h6.6c.8 0 1.5-.7 1.5-1.5L18 7" />
            <path d="M10 11v6" />
            <path d="M14 11v6" />
            @break

        @default
            <circle cx="12" cy="12" r="8.25" />
    @endswitch
</svg>
