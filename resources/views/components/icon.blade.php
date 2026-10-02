{{--
    Inline 24×24 stroke icon (currentColor). <x-icon name="roller" /> · <x-icon name="mail" size="20" />
    Unknown names fall back to the brand triangle. Always decorative (aria-hidden).
--}}
@props([
    'name',
    'size' => null,
])
@php
    $icons = [
        // --- the 18 services -------------------------------------------------
        'roller' => '<rect x="3" y="3.5" width="14" height="5" rx="1.2"/><path d="M17 6h2.5v5.2l-7.5 1.6V15"/><rect x="10.4" y="15" width="3.2" height="6" rx="1"/><path d="M5.5 8.5v2.2M8.5 8.5v1.4" opacity=".6"/>',
        'easel' => '<path d="M12 2.5v2.5"/><rect x="4.5" y="5" width="15" height="10.5" rx=".8"/><path d="m8 15.5-3 6M16 15.5l3 6M12 15.5V20"/><path d="m8 12.5 3-4 2.2 2.6 1.4-1.6 2.4 3"/>',
        'portrait' => '<rect x="3.5" y="2.5" width="17" height="19" rx="1.2"/><path d="M12 6.5 15 9.5 12 12.8 9 9.5Z"/><path d="M6.5 19c.8-3.2 3-4.8 5.5-4.8s4.7 1.6 5.5 4.8"/>',
        'spray' => '<rect x="5" y="9" width="8" height="12.5" rx="1.6"/><path d="M7 9V6.8a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2V9M8 4.8V3h2v1.8"/><path d="M15.5 5.5h.01M18 4h.01M18 7.5h.01M20.5 5.5h.01M16.5 9h.01M20 10h.01"/>',
        'pen-tool' => '<path d="M12 3 4.5 15.5 12 21l7.5-5.5Z"/><circle cx="12" cy="13.2" r="1.7"/><path d="M12 3v8.5"/>',
        'sign' => '<path d="M12 2.5V6M7 6h10"/><path d="M7 6 4.5 9.5 7 13h10l2.5-3.5L17 6"/><path d="M12 13v8.5M9 21.5h6"/><path d="M9.5 9.5h5" opacity=".7"/>',
        'nib' => '<path d="M12 21.5V14"/><path d="M6.5 9.5 12 2.5l5.5 7-2 6.5h-7Z"/><circle cx="12" cy="11.5" r="1.4"/>',
        'tablet' => '<rect x="3" y="4" width="15" height="16.5" rx="2"/><path d="M9.5 17.5h2"/><path d="m21 3-6.5 9.5-1.4 2.6 2.5-1.6L22 4.6Z"/>',
        'chisel' => '<path d="M14.5 2.5 21.5 9.5 18 13 11 6Z"/><path d="m12.5 7.5-8 8L3 21l5.5-1.5 8-8"/><path d="m6.5 13.5 4 4" opacity=".7"/>',
        'vase' => '<path d="M9 2.5h6M9.5 2.5v3C7 7.5 5.5 10.2 5.5 13.5c0 4.4 3 8 6.5 8s6.5-3.6 6.5-8c0-3.3-1.5-6-4-8v-3"/><path d="M6.4 11h11.2M6 15.5h12" opacity=".7"/>',
        'mosaic' => '<path d="M12 2.5 21.5 19.5H2.5Z"/><path d="M7.25 11h9.5M12 2.5 7.25 11 12 19.5 16.75 11Z"/>',
        'frame' => '<rect x="2.5" y="2.5" width="19" height="19" rx=".8"/><rect x="6.5" y="6.5" width="11" height="11"/><path d="m2.5 2.5 4 4M21.5 2.5l-4 4M2.5 21.5l4-4M21.5 21.5l-4-4"/>',
        'sofa' => '<path d="M5 11V8a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 8v3"/><path d="M3 11.5a2 2 0 0 1 4 0V14h10v-2.5a2 2 0 0 1 4 0v6a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5Z"/><path d="M5.5 19v2M18.5 19v2"/>',
        'stage' => '<path d="M2.5 3h19M3.5 3c.5 5 1.8 8 4 10M20.5 3c-.5 5-1.8 8-4 10"/><path d="M2.5 20.5h19M4 17h16"/><path d="m12 7 2.4 4.2H9.6Z"/>',
        'camera' => '<path d="M3 8.5A1.5 1.5 0 0 1 4.5 7h2.2L8.5 4h7l1.8 3h2.2A1.5 1.5 0 0 1 21 8.5v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5Z"/><circle cx="12" cy="13.2" r="4"/><path d="m12 10.6 2.2 3.8H9.8Z"/>',
        'print' => '<path d="M6.5 8.5V3h11v5.5"/><rect x="3" y="8.5" width="18" height="8.5" rx="1.5"/><path d="M6.5 14.5h11V21h-11Z"/><path d="M17 11.5h.01"/>',
        'pencil' => '<path d="M15.5 3.5 20.5 8.5 9 20H4v-5Z"/><path d="m13 6 5 5"/><path d="m4 15 5 5" opacity=".6"/>',
        'van' => '<path d="M2.5 16.5V7.5A1.5 1.5 0 0 1 4 6h10.5v10.5"/><path d="M14.5 9.5h3.6l3.4 3.7v3.3h-2"/><path d="M8.5 16.5h6"/><circle cx="6" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/><path d="m6 9 3 3 3-3" opacity=".7"/>',
        'thread' => '<path d="M17.5 3.5 6 15l3 3L20.5 6.5"/><circle cx="19" cy="5" r="1.4"/><path d="M6 15c-2.2 1.6-3.2 3.3-2.6 4.6.8 1.6 4 .6 6.6-2.6"/><path d="m12 9 3 3" opacity=".6"/>',
        'mask' => '<path d="M3.5 6.5c3-1.4 5.8-2 8.5-2s5.5.6 8.5 2c0 7-3.4 13-8.5 13S3.5 13.5 3.5 6.5Z"/><path d="m7 10 2.4 1.2L7 12.4M17 10l-2.4 1.2 2.4 1.2"/><path d="M9.5 15.5c1.6 1 3.4 1 5 0"/>',
        'book' => '<path d="M12 6.5C10 5 7 4.5 3.5 5v13c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5Z"/><path d="M12 6.5v13"/><path d="m6 10 2-2.5L10 10M14 9.5h4M14 12.5h3" opacity=".7"/>',
        'hands' => '<circle cx="6" cy="6.5" r="2"/><circle cx="18" cy="6.5" r="2"/><path d="m12 3.5 1.8 3.1h-3.6Z"/><path d="M2.5 15.5c.6-3 2-4.5 3.5-4.5 1.6 0 2.8 1.4 3.6 3.1L12 18l2.4-3.9c.8-1.7 2-3.1 3.6-3.1 1.5 0 2.9 1.5 3.5 4.5"/><path d="M8.5 21 12 18l3.5 3"/>',

        // --- interface --------------------------------------------------------
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M20 12H5M11 6l-6 6 6 6"/>',
        'arrow-up' => '<path d="M12 20V5M6 11l6-6 6 6"/>',
        'arrow-down' => '<path d="M12 4v15M6 13l6 6 6-6"/>',
        'arrow-up-right' => '<path d="M6 18 18 6M8.5 6H18v9.5"/>',
        'chevron-down' => '<path d="m6 9.5 6 6 6-6"/>',
        'close' => '<path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/>',
        'menu' => '<path d="M3.5 6.5h17M3.5 12h12M3.5 17.5h7"/>',
        'mail' => '<rect x="2.5" y="5" width="19" height="14" rx="1.6"/><path d="m3.5 6.5 8.5 7 8.5-7"/>',
        'phone' => '<path d="M5 3.5h3.5l1.8 4.6-2.4 1.6a11 11 0 0 0 6.4 6.4l1.6-2.4 4.6 1.8V19a1.6 1.6 0 0 1-1.7 1.6C10.4 20 4 13.6 3.4 5.2A1.6 1.6 0 0 1 5 3.5Z"/>',
        'map-pin' => '<path d="M12 21.5s7-6.2 7-11.8a7 7 0 0 0-14 0c0 5.6 7 11.8 7 11.8Z"/><path d="m12 6.6 2.6 4.4H9.4Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'whatsapp' => '<path d="M3.5 20.5l1.3-4.3A8.6 8.6 0 1 1 8 19.3Z"/><path d="M9 8.5c0 3.4 2.6 6.3 6 6.6l1.2-1.6-1.9-1-1 .9a4.6 4.6 0 0 1-2.3-2.3l.9-1-1-1.9Z"/>',
        'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.1"/><path d="M17.3 6.7h.01"/>',
        'facebook' => '<path d="M14.5 21.5v-8h2.8l.4-3.3h-3.2V8.3c0-.9.3-1.6 1.6-1.6h1.7V3.8a22 22 0 0 0-2.5-.1c-2.5 0-4.1 1.5-4.1 4.2v2.4H8.4v3.3h2.8v8"/>',
        'tiktok' => '<path d="M14 3.5v11.3a3.7 3.7 0 1 1-3.2-3.7"/><path d="M14 3.5c.4 2.6 2.2 4.4 5 4.6"/>',
        'youtube' => '<rect x="2.5" y="5.5" width="19" height="13" rx="3.5"/><path d="m10 9 5 3-5 3Z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3Z"/>',
        'download' => '<path d="M12 3.5v12M6.5 10l5.5 5.5 5.5-5.5"/><path d="M4 20.5h16"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.3L3.5 9"/><path d="M3.5 4v5h5"/><path d="M4 13a8 8 0 0 0 14.3 4.3l2.2-2.3"/><path d="M20.5 20v-5h-5"/>',
        'dice' => '<path d="M12 2.5 20.5 7v10L12 21.5 3.5 17V7Z"/><path d="M3.5 7 12 11.5 20.5 7M12 11.5v10"/><path d="M12 6.6h.01M7.5 12.5h.01M9 16h.01M15.5 13h.01M17 16.5h.01"/>',
        'play' => '<path d="M7 4.5v15L19.5 12Z"/>',
        'pause' => '<path d="M7.5 5v14M16.5 5v14"/>',
        'sparkle' => '<path d="M12 3c.6 4.4 2.6 6.4 7 7-4.4.6-6.4 2.6-7 7-.6-4.4-2.6-6.4-7-7 4.4-.6 6.4-2.6 7-7Z"/><path d="M19 16.5c.2 1.4.9 2.1 2.3 2.3-1.4.2-2.1.9-2.3 2.3-.2-1.4-.9-2.1-2.3-2.3 1.4-.2 2.1-.9 2.3-2.3Z"/>',
        'check' => '<path d="m4.5 12.5 5 5 10-11"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'minus' => '<path d="M5 12h14"/>',
        'quote' => '<path d="M9.5 6.5C6 7.6 4.5 10 4.5 14v3.5h5V12h-3c.2-2 1.4-3.3 3-3.8ZM19.5 6.5c-3.5 1.1-5 3.5-5 7.5v3.5h5V12h-3c.2-2 1.4-3.3 3-3.8Z"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><path d="m12 9 2.6 4.5H9.4Z"/>',
        'palette' => '<path d="M12 3a9 9 0 0 0 0 18c1.4 0 2-1 1.6-2.2-.5-1.4.3-2.8 1.9-2.8H18a3.5 3.5 0 0 0 3.5-3.5C21.5 7 17.2 3 12 3Z"/><path d="M7.5 11h.01M9.5 7h.01M14.5 7h.01M17 10.5h.01"/>',
        'triangle' => '<path d="M12 3 21.5 19.5H2.5Z"/>',
        'wave' => '<path d="M2.5 12c2-4 4-4 6 0s4 4 6 0 4-4 7 0"/><path d="M2.5 17c2-2.5 4-2.5 6 0" opacity=".6"/>',
        'heart' => '<path d="M12 20.5S3.5 15 3.5 9A4.5 4.5 0 0 1 12 6.6 4.5 4.5 0 0 1 20.5 9c0 6-8.5 11.5-8.5 11.5Z"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.6 3.2-5.6 6.5-5.6s5.9 2 6.5 5.6"/><path d="M15.5 4.8a3.5 3.5 0 0 1 0 6.4M18 14.6c2 .7 3.2 2.5 3.5 5.4"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="1.6"/><path d="M3 10h18M8 3v4M16 3v4"/><path d="m12 12.8 2 3.4h-4Z"/>',
        'star' => '<path d="m12 3 2.7 5.8 6.3.7-4.7 4.3 1.3 6.2L12 16.9 6.4 20l1.3-6.2L3 9.5l6.3-.7Z"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5h5"/>',
        'motion' => '<path d="M3 18 9 6l4 8 3-5 5 9"/><path d="M3 21h18" opacity=".6"/>',
        'filter' => '<path d="M3.5 4.5h17L14 12.5v6l-4 2v-8Z"/>',
        'grid' => '<rect x="3" y="3" width="7.5" height="7.5"/><rect x="13.5" y="3" width="7.5" height="7.5"/><rect x="3" y="13.5" width="7.5" height="7.5"/><path d="m13.5 21 3.75-7.5L21 21Z"/>',
    ];
    $markup = $icons[$name] ?? $icons['triangle'];
@endphp
<svg {{ $attributes->class(['icon', 'icon--'.$name]) }} viewBox="0 0 24 24" @if ($size) width="{{ $size }}" height="{{ $size }}" @endif fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $markup !!}</svg>
