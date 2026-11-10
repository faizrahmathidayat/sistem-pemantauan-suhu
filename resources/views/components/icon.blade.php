@props(['name'])

@php
    // Ikon garis sederhana (24x24), digambar sendiri; isinya statis, bukan input pengguna.
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'incident' => '<path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 10v4.5M12 17.5v.01"/>',
        'report' => '<path d="M4 4v16h16"/><path d="M8 16v-5M12 16V8M16 16v-3"/>',
        'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/>',
        'zone' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'building' => '<path d="M4 21V5l8-2v18M12 21h8V10l-8-2"/><path d="M8 9v.01M8 13v.01M8 17v.01M16 14v.01M16 18v.01"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-6 6.5-6s6.5 2.5 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.6 3.5 6"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.2 7.8 8 9 4.8-1.2 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'log' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'logout' => '<path d="M10 4H5v16h5"/><path d="m15 8 4 4-4 4M19 12H9"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'thermometer' => '<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0Z"/>',
        'droplet' => '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
        'print' => '<path d="M7 9V3h10v6M7 17H4v-7h16v7h-3"/><rect x="7" y="14" width="10" height="7" rx="1"/>',
        'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16v.01"/>',
        'pencil' => '<path d="m4 20 1-4L16.5 4.5a2 2 0 0 1 3 3L8 19l-4 1Z"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'size-5']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
