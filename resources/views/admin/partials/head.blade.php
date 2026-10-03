{{--
    <head> content shared by admin.layouts.app and admin.layouts.guest (docs/CMS.md §6).
    Var: $pageTitle (string, may be ''). Only the admin stylesheets: no public loader, cursor or
    page-transition layers (03-layout / 05–07 are not loaded here).
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle !== '' ? $pageTitle.' · ' : '' }}{{ __('admin.brand.title') }} · {{ config('atelier.name', 'Ateliers Pehouet') }}</title>
<meta name="theme-color" content="#000000">
<meta name="color-scheme" content="dark">
<meta name="referrer" content="same-origin">

<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<link rel="preload" href="{{ asset('fonts/audiowide-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="{{ asset('fonts/exo2-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

{{-- html.js / motion preferences before first paint (the same head.js as the site). --}}
<script src="{{ ap_asset('js/head.js') }}"></script>

@foreach (['css/01-tokens.css', 'css/02-base.css', 'css/04-components.css', 'css/admin/admin.css'] as $sheet)
    <link rel="stylesheet" href="{{ ap_asset($sheet) }}">
@endforeach
@stack('styles')
