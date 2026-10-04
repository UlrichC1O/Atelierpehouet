{{--
    Admin layout (docs/CMS.md §6): sidebar + top bar + flash messages + content.
    Screens: @extends('admin.layouts.app') and fill
      @section('title')      page title (also shown in the top bar and the <title>)
      @section('content')    the screen; start with <x-admin.page-head> (the page's only <h1>)
      @section('site_url')   optional: public page opened by "Voir le site" (default: home)
      @push('styles')        extra sheets   <link rel="stylesheet" href="{{ ap_asset('css/admin/x.css') }}">
      @push('scripts')       extra scripts  <script src="{{ ap_asset('js/admin/x.js') }}" defer></script>
                             (sortable lists: @once @push('scripts') … js/admin/sortable.js … @endpush @endonce)
    Never inline <script> code or on* attributes: the CSP only allows files from /js.
    .adm-main carries the accent of the current section (accent-*), inherited by page heads & save bars.
--}}
@php
    // Inline @section values arrive HTML-escaped: decode once so {{ }} below escapes exactly once.
    $sectionText = fn (string $name) => trim(html_entity_decode($__env->yieldContent($name), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $pageTitle = $sectionText('title');
    // "Voir le site": a page of this site only (same origin or a single-slash path; no backslash/whitespace).
    $siteUrl = $sectionText('site_url');
    $siteUrlIsLocal = $siteUrl !== '' && ! preg_match('/[\\\\\x00-\x20\x7f]/', $siteUrl)
        && ($siteUrl === url('/') || str_starts_with($siteUrl, url('/').'/') || (str_starts_with($siteUrl, '/') && ! str_starts_with($siteUrl, '//')));
    $siteUrl = $siteUrlIsLocal ? $siteUrl : route('home');
    $locale = app()->getLocale();
    $user = auth()->user();

    // A database hiccup must never break the shell: the badge simply disappears. Through the
    // circuit breaker when it exists (docs/CMS.md §13 A1), so an outage costs no connect timeout here.
    $countUnread = fn () => \App\Models\ContactMessage::query()->unread()->count();
    $unread = (int) rescue(
        fn () => class_exists(\App\Cms\DatabaseHealth::class) ? app(\App\Cms\DatabaseHealth::class)->attempt($countUnread, 0) : $countUnread(),
        0,
        false,
    );

    $artistsLabel = app('translator')->has('admin_artists.nav') ? __('admin_artists.nav') : __('admin.nav.artists');
    $navDefinition = [
        ['key' => 'main', 'label' => null, 'items' => [
            ['route' => 'admin.dashboard', 'match' => ['admin.dashboard'], 'label' => __('admin.nav.dashboard'), 'icon' => 'dashboard', 'accent' => 'white'],
        ]],
        ['key' => 'content', 'label' => __('admin.nav.groups.content'), 'items' => [
            ['route' => 'admin.texts.index', 'match' => ['admin.texts.*'], 'label' => __('admin.nav.texts'), 'icon' => 'text', 'accent' => 'yellow'],
            ['route' => 'admin.services.index', 'match' => ['admin.services.*'], 'label' => __('admin.nav.services'), 'icon' => 'services', 'accent' => 'red'],
            ['route' => 'admin.media.index', 'match' => ['admin.media.*', 'admin.slots.*'], 'label' => __('admin.nav.media'), 'icon' => 'image', 'accent' => 'blue'],
            ['route' => 'admin.gallery.index', 'match' => ['admin.gallery.*'], 'label' => __('admin.nav.gallery'), 'icon' => 'gallery', 'accent' => 'amber'],
            ['route' => 'admin.pages.index', 'match' => ['admin.pages.*'], 'label' => __('admin.nav.pages'), 'icon' => 'page', 'accent' => 'orange'],
            // Artist pages (docs/ARTISTS.md): only once that module registers its routes.
            ['route' => 'admin.artists.index', 'match' => ['admin.artists.*'], 'label' => $artistsLabel, 'icon' => 'palette', 'accent' => 'orange'],
        ]],
        ['key' => 'contacts', 'label' => __('admin.nav.groups.contacts'), 'items' => [
            ['route' => 'admin.messages.index', 'match' => ['admin.messages.*'], 'label' => __('admin.nav.messages'), 'icon' => 'inbox', 'accent' => 'red', 'badge' => $unread],
        ]],
        ['key' => 'site', 'label' => __('admin.nav.groups.site'), 'items' => [
            ['route' => 'admin.settings.edit', 'match' => ['admin.settings.*'], 'label' => __('admin.nav.settings'), 'icon' => 'settings', 'accent' => 'white'],
            ['route' => 'admin.account.edit', 'match' => ['admin.account.*', 'admin.users.*'], 'label' => __('admin.nav.account'), 'icon' => 'user', 'accent' => 'blue'],
            ['route' => 'admin.maintenance', 'match' => ['admin.maintenance', 'admin.maintenance.*'], 'label' => __('admin.nav.maintenance'), 'icon' => 'database', 'accent' => 'yellow'],
        ]],
    ];

    $admNav = [];
    $admAccent = 'yellow';
    foreach ($navDefinition as $group) {
        $items = [];
        foreach ($group['items'] as $item) {
            if (! \Illuminate\Support\Facades\Route::has($item['route'])) {
                continue;
            }
            $active = request()->routeIs(...$item['match']);
            if ($active) {
                $admAccent = $item['accent'];
            }
            $items[] = [
                'url' => route($item['route']),
                'active' => $active,
                'exact' => request()->routeIs($item['route']),
                'badge' => $item['badge'] ?? 0,
            ] + $item;
        }
        if ($items) {
            $admNav[] = ['key' => $group['key'], 'label' => $group['label'], 'items' => $items];
        }
    }
@endphp
<!DOCTYPE html>
{{-- The admin is designed dark: the public light theme (docs/THEME.md) never applies here (docs/CMS.md §13 G35). --}}
<html lang="{{ str_replace('_', '-', $locale) }}" class="no-js" data-theme="dark" data-theme-lock>
<head>
    @include('admin.partials.head', ['pageTitle' => $pageTitle])
</head>
{{-- data-adm-token-url / data-adm-login-url: admin.js refreshes the CSRF token of long-open pages (§13 D20). --}}
<body class="admin" data-adm-i18n="{{ json_encode(trans('admin.js'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
      @if (\Illuminate\Support\Facades\Route::has('admin.token')) data-adm-token-url="{{ route('admin.token') }}" @endif
      data-adm-login-url="{{ route('admin.login') }}">
    @includeIf('partials.signature-sprite')
    <a class="skip-link" href="#adm-main">{{ __('admin.a11y.skip') }}</a>

    <div class="adm-shell">
        @include('admin.partials.sidebar', ['nav' => $admNav])
        <div class="adm-backdrop" data-adm-nav-close aria-hidden="true"></div>

        <div class="adm-main accent-{{ $admAccent }}">
            @include('admin.partials.copy-warning')
            @include('admin.partials.topbar', ['pageTitle' => $pageTitle, 'siteUrl' => $siteUrl, 'user' => $user])

            <main id="adm-main" class="adm-content" tabindex="-1">
                @include('admin.partials.flash')
                @yield('content')
            </main>
        </div>
    </div>

    <div class="adm-toasts" data-adm-toasts aria-live="polite" aria-relevant="additions"></div>

    <script src="{{ ap_asset('js/core.js') }}" defer></script>
    <script src="{{ ap_asset('js/admin/admin.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
