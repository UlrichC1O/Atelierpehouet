{{--
    Admin icons: the site's 24×24 stroke set (<x-icon>) plus the glyphs only the admin needs.
      <x-admin.icon name="image" />   ·   any <x-icon> name works too (it is the fallback)
    Admin names: dashboard text services image gallery page inbox settings user database logout
    trash upload edit drag search warning info success error eye-off lock undo save link
    move-up move-down focus history home
    Always decorative (aria-hidden): give the control an accessible name.
--}}
@props([
    'name',
    'size' => null,
])
@php
    $glyphs = [
        'dashboard' => '<rect x="3" y="3" width="18" height="18" rx="1.5"/><path d="M10 3v18M10 11.5h11M15.5 11.5V21"/><path d="m3.5 20.5 6-6v6Z" opacity=".55"/>',
        'text' => '<path d="M3.5 6.5V4h11v2.5M9 4v15.5M6.5 19.5h5"/><path d="M15.5 11.5h5M15.5 15.5h5M17.5 19.5h3"/>',
        'services' => '<path d="M12 3 21.5 19.5H2.5Z"/><path d="M12 3v16.5M7.3 11.2H12M12 14.6h5.4"/>',
        'image' => '<rect x="3" y="4.5" width="18" height="15" rx="1.5"/><path d="m3.5 17.5 5.5-6.5 4 4.5 2.5-2.5 5 4.5"/><circle cx="16.2" cy="8.8" r="1.6"/>',
        'gallery' => '<rect x="3" y="3" width="8" height="10.5" rx="1"/><rect x="13" y="3" width="8" height="6.5" rx="1"/><rect x="13" y="11.5" width="8" height="9.5" rx="1"/><rect x="3" y="15.5" width="8" height="5.5" rx="1"/>',
        'page' => '<path d="M6 2.5h8.5L19 7v14.5H6Z"/><path d="M14 2.5V7h5"/><path d="M9 12h7M9 15.5h7M9 19h4"/>',
        'inbox' => '<path d="M3 13.5 5.6 5h12.8L21 13.5V19a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 19Z"/><path d="M3 13.5h5l1.5 2.5h5l1.5-2.5h5"/>',
        'settings' => '<path d="M4 6h8.5M17.5 6H20M4 12h2.5M11.5 12H20M4 18h10.5M19.5 18h.5"/><circle cx="15" cy="6" r="2.2"/><circle cx="9" cy="12" r="2.2"/><circle cx="17" cy="18" r="2.2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 20.5c.8-4 4-6.5 8-6.5s7.2 2.5 8 6.5"/>',
        'database' => '<ellipse cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v13c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-13"/><path d="M4.5 12c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5"/>',
        'logout' => '<path d="M10 20.5H5.5A1.5 1.5 0 0 1 4 19V5a1.5 1.5 0 0 1 1.5-1.5H10"/><path d="M15.5 16.5 20 12l-4.5-4.5M20 12H9.5"/>',
        'trash' => '<path d="M4 6.5h16M9.5 6.5V4h5v2.5M6 6.5l1 14h10l1-14"/><path d="M10 10.5v6.5M14 10.5v6.5"/>',
        'upload' => '<path d="M12 15.5V4M6.5 9.5 12 4l5.5 5.5"/><path d="M4 15v4.5A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'edit' => '<path d="M15.5 3.5 20.5 8.5 9 20H4v-5Z"/><path d="m13 6 5 5"/>',
        'drag' => '<path d="M9 5.5h.01M15 5.5h.01M9 12h.01M15 12h.01M9 18.5h.01M15 18.5h.01" stroke-width="3.2"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/>',
        'warning' => '<path d="M12 3.5 21.5 20H2.5Z"/><path d="M12 9.5V14M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.8h.01"/>',
        'success' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.3 2.8 2.8L16.2 9"/>',
        'error' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'eye-off' => '<path d="m3 3 18 18"/><path d="M10.6 5.6A9.6 9.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.8 16.8 0 0 1-2.7 3.5M6.6 6.6C3.9 8.4 2.5 12 2.5 12S6 18.5 12 18.5a9.4 9.4 0 0 0 4.4-1.1"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10.5" rx="1.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'undo' => '<path d="M9 14.5 4 9.5l5-5"/><path d="M4 9.5h10a6 6 0 0 1 0 12h-3"/>',
        'save' => '<path d="M5 3.5h11.5l4 4V19a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19V5A1.5 1.5 0 0 1 5 3.5Z"/><path d="M7.5 3.5v5h8v-5M7.5 20.5v-6h9v6"/>',
        'link' => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1.2 1.2"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1.2-1.2"/>',
        'move-up' => '<path d="m6 15 6-6 6 6"/>',
        'move-down' => '<path d="m6 9 6 6 6-6"/>',
        'focus' => '<circle cx="12" cy="12" r="6.5"/><path d="M12 2.5v4M12 17.5v4M2.5 12h4M17.5 12h4"/><path d="m12 10.2 1.6 2.8h-3.2Z"/>',
        'history' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3.5 4v4.5H8"/><path d="M12 7.5V12l3 2"/>',
        'home' => '<path d="M3.5 11 12 3.5l8.5 7.5"/><path d="M5.5 9.5v11h13v-11"/><path d="M10 20.5v-5h4v5"/>',
    ];
@endphp
@if (isset($glyphs[$name]))
<svg {{ $attributes->class(['icon', 'icon--'.$name]) }} viewBox="0 0 24 24" @if ($size) width="{{ $size }}" height="{{ $size }}" @endif fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $glyphs[$name] !!}</svg>
@else
<x-icon :name="$name" :size="$size" {{ $attributes }} />
@endif
