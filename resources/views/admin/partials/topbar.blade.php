{{--
    Admin top bar (docs/CMS.md §6): drawer toggle (< 64em), page title, "Voir le site" (new tab),
    FR/EN switch, the signed-in user (→ account) and the logout POST form.
    Vars: $pageTitle (string), $siteUrl (string), $user (?User).
--}}
<header class="adm-topbar">
    <button class="adm-icon-btn adm-topbar__menu" type="button" data-adm-nav-toggle aria-controls="adm-sidebar" aria-expanded="false">
        <x-icon name="menu" />
        <span class="visually-hidden" data-adm-nav-label>{{ __('admin.a11y.nav_open') }}</span>
    </button>

    <p class="adm-topbar__title">{{ $pageTitle !== '' ? $pageTitle : __('admin.brand.title') }}</p>

    <div class="adm-topbar__tools">
        <a class="adm-topbar__link" href="{{ $siteUrl }}" target="_blank" rel="noopener">
            <x-icon name="external" />
            <span class="adm-topbar__label">{{ __('admin.topbar.view_site') }}</span>
            <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
        </a>

        @include('admin.partials.lang-switch')

        @if ($user)
            <a class="adm-user" href="{{ route('admin.account.edit') }}" title="{{ __('admin.a11y.signed_in_as', ['name' => $user->name]) }}">
                <span class="adm-user__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim((string) $user->name) ?: '?', 0, 1)) }}</span>
                <span class="adm-user__name"><span class="visually-hidden">{{ __('admin.topbar.account') }} — </span>{{ $user->name }}</span>
            </a>
        @endif

        <form class="adm-logout" method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="adm-topbar__link" type="submit">
                <x-admin.icon name="logout" />
                <span class="adm-topbar__label">{{ __('admin.topbar.logout') }}</span>
            </button>
        </form>
    </div>
</header>
