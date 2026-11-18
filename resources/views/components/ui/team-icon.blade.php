@props(['name' => 'team'])
{{-- The same 24px outline paths used by Lead Panther navigation and actions. --}}
<svg {{ $attributes->merge(['class' => 'w-4 h-4 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('more')<circle cx="12" cy="5" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="12" cy="19" r="1" />@break
        @case('close')<path d="M6 6l12 12M6 18L18 6" />@break
        @case('edit')<path d="M11 4H4a1 1 0 00-1 1v15a1 1 0 001 1h15a1 1 0 001-1v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />@break
        @case('check')<path d="M5 13l4 4L19 7" />@break
        @case('remove')<path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />@break
        @case('power')<path d="M12 3v9m6.364-6.364a9 9 0 11-12.728 0" />@break
        @case('settings')<path d="M4 7h16M4 17h16M8 4v6m8 4v6" />@break
        @case('add')<path d="M12 4v16M4 12h16" />@break
        @case('copy')<path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />@break
        @case('link')<path d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />@break
        @default<path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
    @endswitch
</svg>
