{{--
    Site header: wordmark, main navigation with the Services mega menu, language switch,
    motion toggle, quote CTA, and the full-screen mobile menu (works without JS via :target).
    Data from App\View\Composers\NavigationComposer: $navServices, $navCategories.
--}}
@php
    $navServices = $navServices ?? collect();
    $navCategories = $navCategories ?? [];
    $byCategory = collect($navCategories)
        ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'accent' => config("atelier.categories.$key.accent", 'yellow'), 'items' => $navServices->where('category', $key)->values()])
        ->filter(fn ($group) => $group['items']->isNotEmpty())
        ->values();
    // Main pages only; the digital atelier and the motion catalogue are linked from the footer.
    $pages = array_values(array_filter([
        ['route' => 'gallery', 'label' => __('ui.nav.gallery')],
        \Illuminate\Support\Facades\Route::has('artists.index')
            ? ['route' => 'artists.index', 'active' => 'artists.*', 'label' => \Illuminate\Support\Facades\Lang::has('artists.nav') ? __('artists.nav') : __('ui.nav.artists')]
            : null,
        ['route' => 'community', 'label' => __('ui.nav.community')],
        ['route' => 'about', 'label' => __('ui.nav.about')],
        ['route' => 'contact', 'label' => __('ui.nav.contact')],
    ]));
    $currentSlug = request()->route('slug');
@endphp
<header class="site-header" data-header>
    <div class="site-header__bar container container--wide">
        <a class="site-header__brand" href="{{ route('home') }}" aria-label="{{ __('ui.a11y.home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>
            <x-logo-wordmark size="header" />
        </a>

        <nav class="site-nav" aria-label="{{ __('ui.a11y.main_nav') }}">
            <ul class="site-nav__list" role="list">
                <li class="site-nav__item site-nav__item--mega" data-mega-item>
                    <a class="site-nav__link" href="{{ route('services.index') }}" @if (request()->routeIs('services.*')) aria-current="page" @endif>{{ __('ui.nav.services') }}</a>
                    <button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="mega-services" data-mega-toggle>
                        <x-icon name="chevron-down" />
                        <span class="visually-hidden">{{ __('ui.a11y.services_nav') }}</span>
                    </button>
                    <div class="mega" id="mega-services" data-mega>
                        <div class="mega__feature accent-red">
                            <span class="eyebrow">{{ __('ui.mega.eyebrow') }}</span>
                            <p class="mega__title">{{ __('ui.mega.title', ['count' => $navServices->count()]) }}</p>
                            <p class="mega__text">{{ __('ui.mega.text') }}</p>
                            <x-logo-mark decorative idle />
                        </div>
                        <div class="mega__columns">
                            @foreach ($byCategory as $index => $group)
                                <div class="mega__group accent-{{ $group['accent'] }}" style="--i: {{ $index }}">
                                    <p class="mega__group-title">{{ $group['label'] }}</p>
                                    <ul class="mega__list" role="list">
                                        @foreach ($group['items'] as $service)
                                            <li>
                                                <a class="mega__link accent-{{ $service['accent'] }}" href="{{ $service['url'] }}" @if ($currentSlug === $service['slug']) aria-current="page" @endif>
                                                    <span class="mega__num" aria-hidden="true">{{ $service['number'] }}</span>
                                                    <span>{{ $service['title'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                            <x-button class="mega__all" :href="route('services.index')" variant="ghost" size="sm" icon="arrow-right">{{ __('ui.nav.all_services') }}</x-button>
                        </div>
                    </div>
                </li>
                @foreach ($pages as $page)
                    <li class="site-nav__item">
                        <a class="site-nav__link" href="{{ route($page['route']) }}" @if (request()->routeIs($page['active'] ?? $page['route'])) aria-current="page" @endif>{{ $page['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="site-header__tools">
            @include('partials.lang-switch')
            @include('partials.motion-toggle', ['compact' => true])
            <x-button class="site-header__cta" :href="route('contact')" size="sm" icon="arrow-right" magnetic>{{ __('ui.cta.quote') }}</x-button>
            <a class="burger" href="#mobile-menu" aria-controls="mobile-menu" aria-expanded="false" data-menu-toggle
               data-label-open="{{ __('ui.a11y.menu_open') }}" data-label-close="{{ __('ui.a11y.menu_close') }}">
                <span class="burger__lines" aria-hidden="true"><span class="burger__line"></span><span class="burger__line"></span><span class="burger__line"></span></span>
                <span class="visually-hidden" data-menu-label>{{ __('ui.a11y.menu_open') }}</span>
            </a>
        </div>
    </div>
</header>

<div class="mobile-menu" id="mobile-menu" data-mobile-menu role="dialog" aria-modal="true" aria-label="{{ __('ui.a11y.mobile_nav') }}">
    <div class="mobile-menu__slices" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
    <a class="mobile-menu__close" href="#">{{ __('ui.a11y.menu_close') }}</a>
    <div class="mobile-menu__inner">
        <nav aria-label="{{ __('ui.a11y.main_nav') }}">
            <ul class="mobile-menu__list" role="list">
                <li class="mobile-menu__item" style="--i: 0"><a class="mobile-menu__link" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ __('ui.nav.home') }}</a></li>
                <li class="mobile-menu__item" style="--i: 1"><a class="mobile-menu__link" href="{{ route('services.index') }}" @if (request()->routeIs('services.*')) aria-current="page" @endif>{{ __('ui.nav.services') }} <small>{{ $navServices->count() }}</small></a></li>
                @foreach ($pages as $page)
                    <li class="mobile-menu__item" style="--i: {{ $loop->index + 2 }}"><a class="mobile-menu__link" href="{{ route($page['route']) }}" @if (request()->routeIs($page['active'] ?? $page['route'])) aria-current="page" @endif>{{ $page['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>

        @if ($byCategory->isNotEmpty())
            <div class="mobile-menu__services" aria-label="{{ __('ui.a11y.services_nav') }}" role="group">
                @foreach ($byCategory as $group)
                    <details class="mobile-menu__group accent-{{ $group['accent'] }}" @if ($group['items']->contains('slug', $currentSlug)) open @endif>
                        <summary>{{ $group['label'] }}</summary>
                        <ul role="list">
                            @foreach ($group['items'] as $service)
                                <li><a href="{{ $service['url'] }}" @if ($currentSlug === $service['slug']) aria-current="page" @endif>{{ $service['number'] }} · {{ $service['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
            </div>
        @endif

        <div class="mobile-menu__tools">
            <x-button :href="route('contact')" icon="arrow-right">{{ __('ui.cta.quote') }}</x-button>
            @include('partials.lang-switch')
            @include('partials.motion-toggle')
        </div>
    </div>
</div>
