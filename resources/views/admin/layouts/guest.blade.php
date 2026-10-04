{{--
    Admin guest layout (docs/CMS.md §6) — the login screen: an art panel (Mondrian fields, the
    animated triangle of the logo) beside a card holding the flash messages and @yield('content').
    Sections: title, content; stacks: styles, scripts. Body class "admin admin--guest".
--}}
@php
    $sectionText = fn (string $name) => trim(html_entity_decode($__env->yieldContent($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $pageTitle = $sectionText('title');
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
{{-- The admin is designed dark: the public light theme (docs/THEME.md) never applies here (docs/CMS.md §13 G35). --}}
<html lang="{{ str_replace('_', '-', $locale) }}" class="no-js" data-theme="dark" data-theme-lock>
<head>
    @include('admin.partials.head', ['pageTitle' => $pageTitle])
</head>
<body class="admin admin--guest" data-adm-i18n="{{ json_encode(trans('admin.js'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    @includeIf('partials.signature-sprite')
    <a class="skip-link" href="#adm-main">{{ __('admin.a11y.skip') }}</a>

    <div class="adm-guest">
        <div class="adm-guest__art ap-anim-scope" aria-hidden="true">
            <div class="adm-guest__fields adm-guest__fields--a">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
            <div class="adm-guest__fields adm-guest__fields--b">
                <span></span><span></span><span></span><span></span>
            </div>
            <span class="adm-guest__slash adm-guest__slash--1"></span>
            <span class="adm-guest__slash adm-guest__slash--2"></span>
            <span class="adm-guest__slash adm-guest__slash--3"></span>
            <div class="adm-guest__brand">
                <x-logo-mark class="adm-guest__mark" animated decorative />
                <p class="adm-guest__word">{{ config('atelier.name', 'Ateliers Pehouet') }}</p>
                <p class="adm-guest__tagline">{{ __('ui.tagline') }}</p>
            </div>
        </div>

        <div class="adm-guest__panel">
            <header class="adm-guest__bar">
                <a class="adm-guest__back" href="{{ route('home') }}">
                    <x-icon name="arrow-left" />
                    <span>{{ __('admin.auth.back_to_site') }}</span>
                </a>
                @include('admin.partials.lang-switch')
            </header>

            <main id="adm-main" class="adm-guest__main" tabindex="-1">
                <div class="adm-guest__card">
                    @include('admin.partials.copy-warning')
                    @include('admin.partials.flash', ['summary' => false])
                    @yield('content')
                </div>
            </main>

            <footer class="adm-guest__foot">
                <span class="adm-mondrian" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></span>
                <span>{{ __('admin.auth.private') }}</span>
            </footer>
        </div>
    </div>

    <div class="adm-toasts" data-adm-toasts aria-live="polite" aria-relevant="additions"></div>

    <script src="{{ ap_asset('js/core.js') }}" defer></script>
    <script src="{{ ap_asset('js/admin/admin.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
