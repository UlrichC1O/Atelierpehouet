{{--
    Admin sidebar (docs/CMS.md §6): brand, the navigation groups built by admin.layouts.app ($nav)
    and a Mondrian strip. Below 64em it is a drawer toggled by [data-adm-nav-toggle] (html.adm-nav-open);
    without JavaScript it simply sits above the content.
    $nav = list of ['key' => ?string, 'label' => ?string, 'items' => list of
           ['url', 'label', 'icon', 'accent', 'active' (bool), 'exact' (bool), 'badge' (int)]]
--}}
<aside id="adm-sidebar" class="adm-sidebar" aria-label="{{ __('admin.a11y.sidebar') }}" data-adm-sidebar>
    <div class="adm-sidebar__head">
        <a class="adm-brand" href="{{ route('admin.dashboard') }}">
            <x-logo-mark class="adm-brand__mark" decorative />
            <span class="adm-brand__text">
                <span class="adm-brand__name">{{ config('atelier.name', 'Ateliers Pehouet') }}</span>
                <span class="adm-brand__sub">{{ __('admin.brand.title') }}</span>
            </span>
        </a>
        <button class="adm-icon-btn adm-sidebar__close" type="button" data-adm-nav-close>
            <x-icon name="close" />
            <span class="visually-hidden">{{ __('admin.a11y.nav_close') }}</span>
        </button>
    </div>

    <nav class="adm-nav" aria-label="{{ __('admin.a11y.nav') }}">
        @foreach ($nav as $group)
            <div class="adm-nav__group">
                @if (! empty($group['label']))
                    <p class="adm-nav__heading" id="adm-nav-{{ $group['key'] }}">{{ $group['label'] }}</p>
                @endif
                <ul class="adm-nav__list" role="list" @if (! empty($group['label'])) aria-labelledby="adm-nav-{{ $group['key'] }}" @endif>
                    @foreach ($group['items'] as $item)
                        <li>
                            <a @class(['adm-nav__link', 'accent-'.$item['accent'], 'is-active' => $item['active']]) href="{{ $item['url'] }}" @if ($item['active']) aria-current="{{ $item['exact'] ? 'page' : 'true' }}" @endif>
                                <span class="adm-nav__icon" aria-hidden="true"><x-admin.icon :name="$item['icon']" /></span>
                                <span class="adm-nav__label">{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="adm-nav__badge" aria-hidden="true">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                    <span class="visually-hidden">({{ trans_choice('admin.nav.unread', $item['badge'], ['count' => $item['badge']]) }})</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="adm-sidebar__foot" aria-hidden="true">
        <span class="adm-mondrian"><span></span><span></span><span></span><span></span><span></span></span>
        <span class="adm-sidebar__tagline">{{ __('admin.brand.tagline') }}</span>
    </div>
</aside>
