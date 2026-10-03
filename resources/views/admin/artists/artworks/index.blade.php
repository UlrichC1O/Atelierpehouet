{{--
    Admin · the artworks of one artist (docs/ARTISTS.md §6.2): upload (one artwork per photo, several at once
    with the CMS uploader, one by one without JavaScript), a library photo as a new artwork, and the ordered
    list (sortable.js autosubmit; without JavaScript the arrows are submit buttons "move" = "{id}:up|down").
    View data: $artist (with artworks_count / exhibitions_count), $artworks (Collection of
    ['model' => App\Models\Artwork, 'media' => ?App\Cms\MediaItem]).
--}}
@extends('admin.layouts.app')

@section('title', __('admin_artists.artworks.title', ['name' => $artist->name]))
@section('site_url', $artist->url())

@php
    $workCount = $artworks->count();
    $libraryChoices = \App\Artists\MediaOptions::list();
    // A refused "library photo" form comes back open, with its errors inside it (old source = library).
    $fromLibrary = old('source') === 'library';
    $here = request()->getRequestUri();
@endphp

@section('content')
    @include('admin.artists.partials.assets', ['sortable' => $workCount > 1])

    <x-admin.page-head :title="__('admin_artists.artworks.title', ['name' => $artist->name])" :lead="__('admin_artists.artworks.lead')"
                       :back="route('admin.artists.index')" />

    @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'artworks', 'toggle' => true])

    <x-admin.card :title="__('admin_artists.artworks.upload_card')" accent="orange" class="adm-artist-upload" id="adm-artist-upload">
        <p class="adm-artist-upload__hint">{{ __('admin_artists.artworks.upload_hint') }}</p>

        @include('admin.media.partials.uploader', [
            'action' => route('admin.artists.artworks.store', $artist),
            'multiple' => true,
            'defaults' => [],
            'redirect' => url()->current(),
            'label' => __('admin_artists.artworks.upload'),
        ])

        @if (! ($fromLibrary && $libraryChoices !== []) && $errors->has('photo'))
            <p class="field__error adm-artist-upload__error" id="field-photo">{{ $errors->first('photo') }}</p>
        @endif

        @if ($libraryChoices !== [])
            <details class="adm-artist-library" @if ($fromLibrary) open @endif>
                <summary class="adm-artist-library__summary">
                    <x-admin.icon name="gallery" />
                    <span>{{ __('admin_artists.artworks.library.summary') }}</span>
                </summary>
                <form class="adm-form adm-artist-library__form" method="POST" action="{{ route('admin.artists.artworks.store', $artist) }}">
                    @csrf
                    <input type="hidden" name="source" value="library">
                    <p class="adm-muted adm-small">{{ __('admin_artists.artworks.library.hint') }}</p>
                    @if ($fromLibrary && $errors->has('photo'))
                        <p class="field__error" id="field-photo">{{ $errors->first('photo') }}</p>
                    @endif
                    @include('admin.artists.partials.media-field', [
                        'name' => 'media_id',
                        'id' => 'artwork-library-input',
                        'label' => __('admin_artists.media.select'),
                        'media' => null,
                        'options' => $libraryChoices,
                        'ratio' => 'natural',
                        'compact' => true,
                    ])
                    <x-admin.field name="title_fr" :label="__('admin_artists.artworks.library.title')" :maxlength="160" autocomplete="off" />
                    <div class="adm-cluster">
                        <button class="btn btn--sm btn--secondary" type="submit">
                            <x-icon name="plus" />
                            <span class="btn__label">{{ __('admin_artists.artworks.library.submit') }}</span>
                        </button>
                    </div>
                </form>
            </details>
        @endif
    </x-admin.card>

    <section class="adm-artist-section" aria-labelledby="adm-artworks-title">
        <div class="adm-artist-section__head">
            <h2 class="adm-artist-section__title" id="adm-artworks-title">
                {{ __('admin_artists.artworks.list_title') }}
                <span class="adm-artist-section__count">{{ $workCount }}</span>
            </h2>
            @if ($workCount > 1)
                <p class="adm-artist-section__hint">{{ __('admin_artists.artworks.order_hint') }}</p>
            @endif
        </div>

        @if ($workCount === 0)
            <x-admin.empty :title="__('admin_artists.artworks.empty.title')" :text="__('admin_artists.artworks.empty.text')" icon="image" />
        @else
            <form class="adm-artist-order" method="POST" action="{{ route('admin.artists.artworks.reorder', $artist) }}">
                @csrf
                <ol class="adm-sortable adm-artist-works" role="list" aria-label="{{ __('admin_artists.a11y.works_order') }}"
                    @if ($workCount > 1) data-sortable data-sortable-autosubmit @endif>
                    @foreach ($artworks as $entry)
                        @php
                            $work = $entry['model'];
                            $workMedia = $entry['media'];
                            $title = $work->text('title') ?? __('admin_artists.artworks.untitled');
                            $details = array_filter([$work->year, $work->text('medium'), $work->dimensions], fn ($part) => is_string($part) && trim($part) !== '');
                            $availability = in_array($work->availability, \App\Models\Artwork::AVAILABILITIES, true) ? $work->availability : 'none';
                            $editUrl = route('admin.artists.artworks.edit', [$artist, $work]);
                        @endphp
                        <li class="adm-sortable__item adm-artist-work" data-sortable-item data-sortable-value="{{ $work->id }}">
                            <input type="hidden" name="order[]" value="{{ $work->id }}">

                            @if ($workCount > 1)
                                <span class="adm-artist-row__grip">
                                    <button class="adm-handle" type="button" data-sortable-handle>
                                        <x-admin.icon name="drag" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.drag', ['name' => $title]) }}</span>
                                    </button>
                                    <span class="adm-sortable__number" data-sortable-number>{{ sprintf('%02d', $loop->iteration) }}</span>
                                </span>
                            @endif

                            <a class="adm-artist-work__media" href="{{ $editUrl }}" tabindex="-1" aria-hidden="true">
                                @if ($workMedia)
                                    <img class="adm-artist-work__img" src="{{ $workMedia->url(480) }}" alt="" width="{{ $workMedia->width }}" height="{{ $workMedia->height }}"
                                         loading="lazy" decoding="async">
                                @else
                                    <span class="adm-artist-work__empty"><x-admin.icon name="image" /></span>
                                @endif
                            </a>

                            <div class="adm-artist-work__body">
                                <h3 class="adm-artist-work__title"><a class="adm-artist-work__link" href="{{ $editUrl }}"><cite>{{ $title }}</cite></a></h3>
                                @if ($details !== [])
                                    <p class="adm-artist-work__meta">{{ implode(' · ', $details) }}</p>
                                @endif
                                <p class="adm-artist-row__badges">
                                    @if ($availability !== 'none')
                                        <span class="adm-artist-availability adm-artist-availability--{{ $availability }}">{{ __('admin_artists.availability.'.$availability) }}</span>
                                    @endif
                                    @unless ($work->is_published)
                                        <x-admin.badge variant="hidden">{{ __('admin_artists.status.hidden') }}</x-admin.badge>
                                    @endunless
                                    @unless ($workMedia)
                                        <x-admin.badge variant="modified">{{ __('admin_artists.status.no_photo') }}</x-admin.badge>
                                    @endunless
                                </p>
                            </div>

                            <div class="adm-artist-work__actions">
                                <a class="btn btn--sm" href="{{ $editUrl }}">
                                    <x-admin.icon name="edit" />
                                    <span class="btn__label">{{ __('admin_artists.actions.edit') }}<span class="visually-hidden"> — {{ $title }}</span></span>
                                </a>
                                <button class="btn btn--sm btn--ghost adm-artist-work__delete" type="submit" form="adm-artwork-delete-{{ $work->id }}">
                                    <x-admin.icon name="trash" />
                                    <span class="btn__label">{{ __('admin_artists.actions.delete') }}<span class="visually-hidden"> — {{ $title }}</span></span>
                                </button>
                            </div>

                            @if ($workCount > 1)
                                <span class="adm-sortable__moves adm-artist-row__moves">
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $work->id }}:up" data-move="up"
                                            @if ($loop->first) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-up" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.move_up', ['name' => $title]) }}</span>
                                    </button>
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $work->id }}:down" data-move="down"
                                            @if ($loop->last) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-down" />
                                        <span class="visually-hidden">{{ __('admin_artists.a11y.move_down', ['name' => $title]) }}</span>
                                    </button>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </form>

            @foreach ($artworks as $entry)
                @php
                    $work = $entry['model'];
                @endphp
                <form id="adm-artwork-delete-{{ $work->id }}" method="POST" action="{{ route('admin.artists.artworks.destroy', [$artist, $work]) }}"
                      data-confirm="{{ __('admin_artists.artworks.delete_confirm', ['title' => $work->text('title') ?? __('admin_artists.artworks.untitled')]) }}"
                      data-confirm-label="{{ __('admin_artists.actions.delete') }}" data-confirm-variant="danger" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        @endif
    </section>
@endsection
