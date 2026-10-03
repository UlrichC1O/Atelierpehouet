{{--
    Ateliers Pehouet — master layout.
    Pages: @extends('layouts.app') and fill these sections/stacks:
      @section('title')            page title (without the brand, it is appended)
      @section('meta_description') ≤ 160 chars
      @section('og_image')         absolute URL (optional)
      @section('body_class')       e.g. "page-home"
      @section('content')          page markup
      @push('styles')              page stylesheets  <link rel="stylesheet" href="{{ ap_asset('css/pages/x.css') }}">
      @push('scripts')             page scripts      <script src="{{ ap_asset('js/x.js') }}" defer></script>
      @push('head')                extra <head> tags (JSON-LD, preloads)
    Never write inline <script> code or on* attributes: the CSP only allows scripts from /js.
--}}
@php
    // Inline @section values arrive HTML-escaped: decode once so {{ }} below escapes exactly once.
    $sectionText = fn (string $name) => trim(html_entity_decode($__env->yieldContent($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $brand = config('atelier.name');
    $pageTitle = $sectionText('title');
    $fullTitle = $pageTitle !== '' ? $pageTitle.' · '.$brand : $brand.' — '.__('ui.tagline');
    $description = $sectionText('meta_description') ?: __('ui.meta.description');
    $ogImage = $sectionText('og_image') ?: asset('images/og-ateliers-pehouet.jpg');
    $locale = app()->getLocale();
    // French is the main language: clean URLs are French (and x-default); English adds ?lang=en.
    $frenchUrl = url()->current();
    $englishUrl = $frenchUrl.'?lang=en';
    $canonical = $locale === 'en' ? $englishUrl : $frenchUrl;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="fr" href="{{ $frenchUrl }}">
    <link rel="alternate" hreflang="en" href="{{ $englishUrl }}">
    <link rel="alternate" hreflang="x-default" href="{{ $frenchUrl }}">
    <meta name="theme-color" content="#000000">
    <meta name="color-scheme" content="dark">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:locale" content="{{ $locale === 'en' ? 'en_US' : 'fr_FR' }}">
    <meta property="og:locale:alternate" content="{{ $locale === 'en' ? 'fr_FR' : 'en_US' }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preload" href="{{ asset('fonts/audiowide-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/exo2-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- Sets html.js / motion classes / loader state before first paint. --}}
    <script src="{{ ap_asset('js/head.js') }}"></script>

    @foreach (['01-tokens', '02-base', '03-layout', '04-components', '05-anim-brand', '06-anim-ambient', '07-anim-ui'] as $sheet)
        <link rel="stylesheet" href="{{ ap_asset('css/'.$sheet.'.css') }}">
    @endforeach
    @stack('styles')

    @include('partials.jsonld')
    @stack('head')
</head>
<body class="@yield('body_class')">
    @include('partials.signature-sprite')
    <a class="skip-link" href="#main">{{ __('ui.a11y.skip') }}</a>

    @include('partials.loader')
    @include('partials.header')

    <main id="main" class="site-main" tabindex="-1">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.fx')

    <script src="{{ ap_asset('js/core.js') }}" defer></script>
    <script src="{{ ap_asset('js/nav.js') }}" defer></script>
    <script src="{{ ap_asset('js/fx.js') }}" defer></script>
    <script src="{{ ap_asset('js/effects.js') }}" defer></script>
    <script src="{{ ap_asset('js/loader.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
