@props(['name'])

@php
    $paths = [
        'home' => '<path d="M4 11.5 12 4l8 7.5" /><path d="M6 10v9a1 1 0 0 0 1 1h4v-6h2v6h4a1 1 0 0 0 1-1v-9" />',
        'chart-bar' => '<path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-7" /><path d="M4 20h16" />',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2" /><path d="M3.5 9.5h17" /><path d="M8 3v4" /><path d="M16 3v4" />',
        'users' => '<circle cx="9" cy="8" r="3" /><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" /><path d="M15.5 5.5a3 3 0 0 1 0 5.9" /><path d="M15 14.2c2.4.5 4.5 2.3 4.5 4.8" />',
        'photo' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2" /><circle cx="9" cy="10" r="1.75" /><path d="M4 18.5 9.5 13l3 3 3.5-4L20 17" />',
        'briefcase' => '<rect x="3.5" y="7.5" width="17" height="12" rx="2" /><path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5" /><path d="M3.5 13h17" />',
        'currency' => '<circle cx="12" cy="12" r="8.5" /><path d="M12 7.5v9" /><path d="M14.5 9.5c0-1.1-1.1-2-2.5-2s-2.5.8-2.5 1.9c0 2.7 5 1.4 5 4.1 0 1.1-1.1 2-2.5 2s-2.5-.9-2.5-2" />',
        'bolt' => '<path d="M13 3 5 13.5h5.5L11 21l8-10.5h-5.5L13 3Z" />',
        'document' => '<path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" /><path d="M14 3.5V8h4" /><path d="M9 13h6" /><path d="M9 16.5h6" />',
        'flag' => '<path d="M6 3v18" /><path d="M6 4.5c1.5-1 3.5-1 5 0s3.5 1 5 0v9c-1.5 1-3.5 1-5 0s-3.5-1-5 0" />',
        'user-group' => '<circle cx="8.5" cy="8" r="3" /><circle cx="16" cy="9" r="2.5" /><path d="M3 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5" /><path d="M14.5 14.5c2.2.4 4 2 4 4.5" />',
        'cloud' => '<path d="M7.5 18a4 4 0 0 1-.5-8 5.5 5.5 0 0 1 10.6-1.7A4.25 4.25 0 0 1 17 18H7.5Z" />',
        'clipboard' => '<rect x="5.5" y="4.5" width="13" height="16" rx="2" /><rect x="9" y="3" width="6" height="3" rx="1" /><path d="M8.5 11h7" /><path d="M8.5 14.5h7" /><path d="M8.5 18h4.5" />',
        'shield' => '<path d="M12 3.5 19 6.3v5.3c0 4.6-3 7.9-7 9.4-4-1.5-7-4.8-7-9.4V6.3L12 3.5Z" /><path d="m9 12 2 2 4-4.3" />',
        'lock' => '<rect x="5.5" y="10.5" width="13" height="9.5" rx="2" /><path d="M8.5 10.5V7.5a3.5 3.5 0 0 1 7 0v3" />',
        'menu' => '<path d="M4 6.5h16" /><path d="M4 12h16" /><path d="M4 17.5h16" />',
        'close' => '<path d="m6 6 12 12" /><path d="m18 6-12 12" />',
        'chevron-down' => '<path d="m6 9 6 6 6-6" />',
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2.5h-15L6 16Z" /><path d="M10 20a2 2 0 0 0 4 0" />',
        'logout' => '<path d="M9 4.5H6a1.5 1.5 0 0 0-1.5 1.5v12A1.5 1.5 0 0 0 6 19.5h3" /><path d="M14 15.5 19 12l-5-3.5" /><path d="M19 12H9" />',
        'switch' => '<path d="m7 4-3.5 3.5L7 11" /><path d="M3.5 7.5h11" /><path d="m17 20 3.5-3.5L17 13" /><path d="M20.5 16.5h-11" />',
        'empty' => '<rect x="4" y="7" width="16" height="13" rx="2" /><path d="M4 11h16" /><path d="M9 4.5v4" /><path d="M15 4.5v4" />',
        'ban' => '<circle cx="12" cy="12" r="8.5" /><path d="m6.5 6.5 11 11" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>
    {!! $paths[$name] ?? $paths['empty'] !!}
</svg>
