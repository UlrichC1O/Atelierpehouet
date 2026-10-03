{{--
    Sections of one artist page in the admin (docs/ARTISTS.md §6.2): tabs-as-links "Profil",
    "Œuvres (n)", "Expositions (n)", the status badges and "Voir la page" / "Aperçu" (new tab).
      @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'artworks', 'toggle' => true])
    Vars: $artist (App\Models\Artist with artworks_count / exhibitions_count), $current
    (profile|artworks|exhibitions), $toggle (bool, default false: a "Publier / Masquer" button that
    posts to admin.artists.toggle and comes back here).
--}}
@php
    $current = in_array($current ?? null, ['profile', 'artworks', 'exhibitions'], true) ? $current : 'profile';
    $published = (bool) $artist->is_published;
    $artworkCount = (int) ($artist->artworks_count ?? $artist->artworks()->count());
    $exhibitionCount = (int) ($artist->exhibitions_count ?? $artist->exhibitions()->count());
    $tabs = [
        'profile' => ['url' => route('admin.artists.edit', $artist), 'label' => __('admin_artists.subnav.profile'), 'count' => null],
        'artworks' => ['url' => route('admin.artists.artworks.index', $artist), 'label' => __('admin_artists.subnav.artworks'), 'count' => $artworkCount],
        'exhibitions' => ['url' => route('admin.artists.exhibitions.index', $artist), 'label' => __('admin_artists.subnav.exhibitions'), 'count' => $exhibitionCount],
    ];
    $subnavToggle = (bool) ($toggle ?? false);
@endphp
<nav class="adm-artist-subnav" aria-label="{{ __('admin_artists.subnav.label', ['name' => $artist->name]) }}">
    <ul class="adm-artist-subnav__tabs" role="list">
        @foreach ($tabs as $key => $tab)
            <li>
                <a @class(['adm-artist-subnav__tab', 'is-current' => $key === $current]) href="{{ $tab['url'] }}" @if ($key === $current) aria-current="page" @endif>
                    <span>{{ $tab['label'] }}</span>
                    @if ($tab['count'] !== null)
                        <span class="adm-artist-subnav__count">{{ $tab['count'] }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>

    <div class="adm-artist-subnav__side">
        @if ($artist->is_example)
            <x-admin.badge variant="custom">{{ __('admin_artists.status.example') }}</x-admin.badge>
        @endif
        <x-admin.badge :variant="$published ? 'success' : 'hidden'">{{ $published ? __('admin_artists.status.published') : __('admin_artists.status.draft') }}</x-admin.badge>

        @if ($subnavToggle)
            <form class="adm-artist-subnav__form" method="POST" action="{{ route('admin.artists.toggle', $artist) }}">
                @csrf
                <input type="hidden" name="redirect" value="{{ request()->getRequestUri() }}">
                <button class="btn btn--sm {{ $published ? 'btn--ghost' : '' }}" type="submit">
                    <x-admin.icon :name="$published ? 'eye-off' : 'eye'" />
                    <span class="btn__label">{{ $published ? __('admin_artists.actions.hide') : __('admin_artists.actions.publish') }}</span>
                </button>
            </form>
        @endif

        <a class="btn btn--sm btn--ghost" href="{{ $artist->url() }}" target="_blank" rel="noopener">
            <x-icon name="external" />
            <span class="btn__label">{{ $published ? __('admin_artists.actions.view') : __('admin_artists.actions.preview') }}</span>
            <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
        </a>
    </div>
</nav>
