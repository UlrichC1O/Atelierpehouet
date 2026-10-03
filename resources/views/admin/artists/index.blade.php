{{--
    Admin · Artistes (docs/ARTISTS.md §6.2): every artist page, in the order of the site.
    View data: $artists (Collection<App\Models\Artist> ordered, with artworks_count, exhibitions_count and the
    artworks relation loaded), $hasExample (bool).
    The list is one reorder form (sortable.js, autosubmit); without JavaScript its arrows are submit buttons
    ("move" = "{id}:up|down"). The publish / hide buttons belong to small forms after the list (form="…").
--}}
@extends('admin.layouts.app')

@section('title', __('admin_artists.index.title'))
@section('site_url', \Illuminate\Support\Facades\Route::has('artists.index') ? route('artists.index') : url('/'))

@php
    $artistCount = $artists->count();
    $publishedCount = $artists->filter(fn ($artist) => (bool) $artist->is_published)->count();
    $canCreateExample = ! $hasExample && is_file(resource_path('content/artists/example.php'));
    $accentKeys = (array) config('atelier.accents', []);
    $here = request()->getRequestUri();
@endphp

@section('content')
    @include('admin.artists.partials.assets', ['sortable' => $artistCount > 1])

    <x-admin.page-head :title="__('admin_artists.index.title')" :lead="__('admin_artists.index.lead')">
        <a class="btn btn--sm" href="{{ route('admin.artists.create') }}">
            <x-icon name="plus" />
            <span class="btn__label">{{ __('admin_artists.actions.add') }}</span>
        </a>
        @if ($canCreateExample && $artistCount > 0)
            <form class="adm-artist-inline-form" method="POST" action="{{ route('admin.artists.example') }}">
                @csrf
                <button class="btn btn--sm btn--ghost" type="submit" title="{{ __('admin_artists.index.example_hint') }}">
                    <x-icon name="sparkle" />
                    <span class="btn__label">{{ __('admin_artists.actions.example') }}</span>
                </button>
            </form>
        @endif
    </x-admin.page-head>

    @if ($artistCount === 0)
        <x-admin.empty :title="__('admin_artists.index.empty.title')" :text="$canCreateExample ? __('admin_artists.index.empty.text') : __('admin_artists.index.empty.text_plain')" icon="palette">
            <a class="btn btn--sm" href="{{ route('admin.artists.create') }}">
                <x-icon name="plus" />
                <span class="btn__label">{{ __('admin_artists.actions.add') }}</span>
            </a>
            @if ($canCreateExample)
                <form class="adm-artist-inline-form" method="POST" action="{{ route('admin.artists.example') }}">
                    @csrf
                    <button class="btn btn--sm btn--ghost" type="submit">
                        <x-icon name="sparkle" />
                        <span class="btn__label">{{ __('admin_artists.actions.example') }}</span>
                    </button>
                </form>
            @endif
        </x-admin.empty>
    @else
        <section class="adm-artist-section" aria-labelledby="adm-artists-list-title">
            <div class="adm-artist-section__head">
                <h2 class="adm-artist-section__title" id="adm-artists-list-title">
                    {{ __('admin_artists.index.summary', [
                        'artists' => trans_choice('admin_artists.counts.artists', $artistCount, ['count' => $artistCount]),
                        'published' => trans_choice('admin_artists.counts.published', $publishedCount, ['count' => $publishedCount]),
                    ]) }}
                </h2>
                @if ($artistCount > 1)
                    <p class="adm-artist-section__hint">{{ __('admin_artists.index.order_hint') }}</p>
                @endif
            </div>

            <form class="adm-artist-order" method="POST" action="{{ route('admin.artists.reorder') }}">
                @csrf
                <ol class="adm-sortable adm-artist-list" role="list" aria-label="{{ __('admin_artists.a11y.order') }}"
                    @if ($artistCount > 1) data-sortable data-sortable-autosubmit @endif>
                    @foreach ($artists as $artist)
                        @php
                            $accent = in_array($artist->accent, $accentKeys, true) ? $artist->accent : 'yellow';
                            $thumb = \App\Artists\ArtistDirectory::media($artist->photoId());
                            $published = (bool) $artist->is_published;
                            $discipline = $artist->text('discipline');
                            $editUrl = route('admin.artists.edit', $artist);
                        @endphp
                        <li class="adm-sortable__item adm-artist-row accent-{{ $accent }}" data-sortable-item data-sortable-value="{{ $artist->id }}">
                            <input type="hidden" name="order[]" value="{{ $artist->id }}">

                            @if ($artistCount > 1)
                                <span class="adm-artist-row__grip">
                                    <button class="adm-handle" type="button" data-sortable-handle>
                                        <x-admin.icon name="drag" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.drag', ['name' => $artist->name]) }}</span>
                                    </button>
                                    <span class="adm-sortable__number" data-sortable-number>{{ sprintf('%02d', $loop->iteration) }}</span>
                                </span>
                            @endif

                            <a class="adm-artist-row__media" href="{{ $editUrl }}" tabindex="-1" aria-hidden="true">
                                @if ($thumb)
                                    <img class="adm-artist-row__img" src="{{ $thumb->url(480) }}" alt="" width="96" height="120" loading="lazy" decoding="async"
                                         style="object-position: {{ $thumb->objectPosition() }}">
                                @else
                                    <span class="adm-artist-monogram">{{ $artist->initials() }}</span>
                                @endif
                            </a>

                            <div class="adm-artist-row__body">
                                <h3 class="adm-artist-row__name"><a class="adm-artist-row__link" href="{{ $editUrl }}">{{ $artist->name }}</a></h3>
                                <p class="adm-artist-row__meta">
                                    @if ($discipline)
                                        <span>{{ $discipline }}</span>
                                    @else
                                        <span class="adm-artist-row__missing">{{ __('admin_artists.index.no_discipline') }}</span>
                                    @endif
                                    @if ($artist->location)
                                        <span class="adm-artist-row__place"><x-icon name="map-pin" />{{ $artist->location }}</span>
                                    @endif
                                </p>
                                <p class="adm-artist-row__counts">
                                    <a class="adm-artist-row__count" href="{{ route('admin.artists.artworks.index', $artist) }}">{{ trans_choice('admin_artists.counts.artworks', (int) $artist->artworks_count, ['count' => (int) $artist->artworks_count]) }}</a>
                                    <span aria-hidden="true">·</span>
                                    <a class="adm-artist-row__count" href="{{ route('admin.artists.exhibitions.index', $artist) }}">{{ trans_choice('admin_artists.counts.exhibitions', (int) $artist->exhibitions_count, ['count' => (int) $artist->exhibitions_count]) }}</a>
                                </p>
                                <div class="adm-artist-row__badges">
                                    <x-admin.badge :variant="$published ? 'success' : 'hidden'">{{ $published ? __('admin_artists.status.published') : __('admin_artists.status.draft') }}</x-admin.badge>
                                    @if ($artist->is_example)
                                        <x-admin.badge variant="custom">{{ __('admin_artists.status.example') }}</x-admin.badge>
                                    @endif
                                    <button class="adm-artist-row__toggle" type="submit" form="adm-artist-toggle-{{ $artist->id }}">
                                        <x-admin.icon :name="$published ? 'eye-off' : 'eye'" />
                                        <span>{{ $published ? __('admin_artists.actions.hide') : __('admin_artists.actions.publish') }}<span class="visually-hidden"> — {{ $artist->name }}</span></span>
                                    </button>
                                </div>
                            </div>

                            <div class="adm-artist-row__actions">
                                <a class="btn btn--sm" href="{{ $editUrl }}">
                                    <x-admin.icon name="edit" />
                                    <span class="btn__label">{{ __('admin_artists.actions.edit') }}<span class="visually-hidden"> — {{ $artist->name }}</span></span>
                                </a>
                                <a class="btn btn--sm btn--ghost" href="{{ route('admin.artists.artworks.index', $artist) }}">
                                    <x-admin.icon name="image" />
                                    <span class="btn__label">{{ __('admin_artists.actions.artworks') }}<span class="visually-hidden"> — {{ $artist->name }}</span></span>
                                </a>
                                <a class="btn btn--sm btn--ghost" href="{{ route('admin.artists.exhibitions.index', $artist) }}">
                                    <x-icon name="calendar" />
                                    <span class="btn__label">{{ __('admin_artists.actions.exhibitions') }}<span class="visually-hidden"> — {{ $artist->name }}</span></span>
                                </a>
                                <a class="btn btn--sm btn--ghost" href="{{ $artist->url() }}" target="_blank" rel="noopener">
                                    <x-icon name="external" />
                                    <span class="btn__label">{{ $published ? __('admin_artists.actions.view') : __('admin_artists.actions.preview') }}<span class="visually-hidden"> — {{ $artist->name }} ({{ __('admin.a11y.new_tab') }})</span></span>
                                </a>
                            </div>

                            @if ($artistCount > 1)
                                <span class="adm-sortable__moves adm-artist-row__moves">
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $artist->id }}:up" data-move="up"
                                            @if ($loop->first) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-up" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.move_up', ['name' => $artist->name]) }}</span>
                                    </button>
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $artist->id }}:down" data-move="down"
                                            @if ($loop->last) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-down" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.move_down', ['name' => $artist->name]) }}</span>
                                    </button>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </form>

            @foreach ($artists as $artist)
                <form id="adm-artist-toggle-{{ $artist->id }}" method="POST" action="{{ route('admin.artists.toggle', $artist) }}" hidden>
                    @csrf
                    <input type="hidden" name="redirect" value="{{ $here }}">
                </form>
            @endforeach
        </section>
    @endif
@endsection
