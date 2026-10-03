{{--
    Admin · one artwork (docs/ARTISTS.md §6.2): the photo at its natural ratio with "Remplacer le fichier"
    (CMS uploader posting to admin.artists.artworks.image) and a link to its library page; the record form
    (PUT: titles, year, medium, dimensions, availability, notes, another library photo, visibility); delete.
    View data: $artist (with counts), $artwork, $media (?App\Cms\MediaItem), $mediaOptions (id ⇒ label),
    $availabilities (list<string>).
--}}
@extends('admin.layouts.app')

@php
    $workTitle = $artwork->text('title') ?? __('admin_artists.artworks.untitled');
    $availabilityOld = old('availability');
    $availabilityCurrent = is_string($availabilityOld) && $availabilityOld !== '' ? $availabilityOld : ($artwork->availability ?: 'none');
    $availabilityError = $errors->first('availability');
    $mediaEditUrl = $media && \Illuminate\Support\Facades\Route::has('admin.media.edit') && view()->exists('admin.media.edit') ? route('admin.media.edit', $media->id) : null;
@endphp

@section('title', $workTitle)
@section('site_url', $artist->url())

@section('content')
    @include('admin.artists.partials.assets')

    <x-admin.page-head :title="$workTitle" :lead="__('admin_artists.artworks.edit.lead', ['name' => $artist->name])"
                       :back="route('admin.artists.artworks.index', $artist)" class="adm-artist-work-head" />

    @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'artworks'])

    <div class="adm-artist-work-layout">
        <div class="adm-artist-work-layout__media">
            <x-admin.card :title="__('admin_artists.artworks.edit.photo')" class="adm-artist-photo">
                @if ($media)
                    <figure class="adm-artist-photo__figure">
                        <div class="adm-artist-photo__mat">
                            <img class="adm-artist-photo__img" src="{{ $media->url(960) }}" srcset="{{ $media->srcset() }}"
                                 sizes="(min-width: 80em) 34rem, (min-width: 64em) 40vw, 92vw"
                                 width="{{ $media->width }}" height="{{ $media->height }}"
                                 alt="{{ __('admin_artists.media.current', ['alt' => $media->alt()]) }}" decoding="async">
                        </div>
                        <figcaption class="adm-artist-photo__caption">
                            <span>{{ implode(' · ', array_filter([$media->originalName, __('admin_artists.media.size', ['width' => $media->width, 'height' => $media->height])])) }}</span>
                            <span class="adm-artist-photo__note">{{ __('admin_artists.artworks.edit.natural') }}</span>
                        </figcaption>
                    </figure>
                    @if ($mediaEditUrl)
                        <p>
                            <a class="adm-artist-media__edit" href="{{ $mediaEditUrl }}">
                                <x-admin.icon name="focus" />
                                <span>{{ __('admin_artists.media.edit') }}</span>
                            </a>
                        </p>
                    @endif
                @else
                    <div class="adm-artist-photo__none">
                        <span class="adm-artist-media__tri" aria-hidden="true"></span>
                        <p>{{ __('admin_artists.artworks.edit.no_photo') }}</p>
                    </div>
                @endif

                <div class="adm-artist-photo__replace">
                    @include('admin.media.partials.uploader', [
                        'action' => route('admin.artists.artworks.image', [$artist, $artwork]),
                        'multiple' => false,
                        'defaults' => [],
                        'redirect' => url()->current(),
                        'label' => $media ? __('admin_artists.artworks.edit.replace') : __('admin_artists.artworks.edit.add_photo'),
                    ])
                    @if ($errors->has('photo'))
                        <p class="field__error" id="field-photo">{{ $errors->first('photo') }}</p>
                    @endif
                    @if ($media)
                        <p class="field__hint">{{ __('admin_artists.artworks.edit.replace_hint') }}</p>
                    @endif
                </div>
            </x-admin.card>
        </div>

        <div class="adm-artist-work-layout__form">
            <form class="adm-form adm-artist-form" method="POST" action="{{ route('admin.artists.artworks.update', [$artist, $artwork]) }}" data-adm-dirty>
                @csrf
                @method('PUT')

                <x-admin.card :title="__('admin_artists.artworks.edit.details')">
                    <div class="adm-pair">
                        <x-admin.field name="title_fr" lang="fr" :label="__('admin_artists.artworks.fields.title.label')" :value="$artwork->title_fr"
                                       :hint="__('admin_artists.artworks.fields.title.hint')" :maxlength="160" required />
                        <x-admin.field name="title_en" lang="en" :label="__('admin_artists.artworks.fields.title.label')" :value="$artwork->title_en"
                                       :hint="__('admin_artists.fields.en_hint')" :maxlength="160" />
                    </div>

                    <div class="adm-grid adm-grid--2">
                        <x-admin.field name="year" :label="__('admin_artists.artworks.fields.year.label')" :value="$artwork->year"
                                       :hint="__('admin_artists.artworks.fields.year.hint')" :maxlength="20" inputmode="numeric" autocomplete="off" />
                        <x-admin.field name="dimensions" :label="__('admin_artists.artworks.fields.dimensions.label')" :value="$artwork->dimensions"
                                       :hint="__('admin_artists.artworks.fields.dimensions.hint')" :maxlength="80" autocomplete="off" />
                    </div>

                    <div class="adm-pair">
                        <x-admin.field name="medium_fr" lang="fr" :label="__('admin_artists.artworks.fields.medium.label')" :value="$artwork->medium_fr"
                                       :hint="__('admin_artists.artworks.fields.medium.hint')" :maxlength="160" />
                        <x-admin.field name="medium_en" lang="en" :label="__('admin_artists.artworks.fields.medium.label')" :value="$artwork->medium_en"
                                       :hint="__('admin_artists.fields.en_hint')" :maxlength="160" />
                    </div>

                    <fieldset @class(['choices', 'adm-artist-availabilities', 'field--invalid' => $availabilityError]) id="field-availability"
                              aria-describedby="field-availability-hint{{ $availabilityError ? ' field-availability-error' : '' }}">
                        <legend class="field__label">{{ __('admin_artists.artworks.fields.availability.label') }}</legend>
                        <div class="choices__list">
                            @foreach ($availabilities as $availability)
                                <label class="choice adm-artist-availability-choice adm-artist-availability-choice--{{ $availability }}">
                                    <input type="radio" name="availability" value="{{ $availability }}" @checked($availabilityCurrent === $availability)>
                                    <span>{{ __('admin_artists.availability.'.$availability) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="field__hint" id="field-availability-hint">{{ __('admin_artists.artworks.fields.availability.hint') }}</p>
                        @if ($availabilityError)
                            <p class="field__error" id="field-availability-error">{{ $availabilityError }}</p>
                        @endif
                    </fieldset>

                    <div class="adm-pair">
                        <x-admin.field name="description_fr" type="textarea" :rows="4" lang="fr" :label="__('admin_artists.artworks.fields.description.label')"
                                       :value="$artwork->description_fr" :hint="__('admin_artists.artworks.fields.description.hint')" :maxlength="1500" />
                        <x-admin.field name="description_en" type="textarea" :rows="4" lang="en" :label="__('admin_artists.artworks.fields.description.label')"
                                       :value="$artwork->description_en" :hint="__('admin_artists.fields.en_hint')" :maxlength="1500" />
                    </div>
                </x-admin.card>

                <x-admin.card :title="__('admin_artists.artworks.edit.publication')">
                    @include('admin.artists.partials.media-field', [
                        'name' => 'media_id',
                        'id' => 'artwork-media-input',
                        'label' => __('admin_artists.artworks.edit.other_photo'),
                        'media' => $media,
                        'options' => $mediaOptions,
                        'ratio' => 'natural',
                        'compact' => true,
                        'hint' => __('admin_artists.artworks.edit.other_photo_hint'),
                        'editLink' => false,
                    ])
                    <x-admin.toggle name="is_published" :label="__('admin_artists.artworks.fields.is_published.label')" :checked="(bool) $artwork->is_published"
                                    :hint="__('admin_artists.artworks.fields.is_published.hint')" />
                </x-admin.card>

                <x-admin.savebar :back="route('admin.artists.artworks.index', $artist)" />
            </form>

            <x-admin.card :title="__('admin_artists.artworks.edit.danger')" accent="red" class="adm-artist-danger" id="adm-artist-danger">
                <p class="adm-artist-danger__text">{{ __('admin_artists.artworks.edit.delete_text') }}</p>
                <x-admin.confirm :action="route('admin.artists.artworks.destroy', [$artist, $artwork])" :label="__('admin_artists.artworks.edit.delete')"
                                 :message="__('admin_artists.artworks.edit.delete_confirm', ['title' => $workTitle])" class="adm-artist-danger__form">
                    @if ($media)
                        <label class="checkbox adm-artist-danger__check">
                            <input type="checkbox" name="delete_photo" value="1">
                            <span>{{ __('admin_artists.artworks.edit.delete_photo') }}</span>
                        </label>
                    @endif
                </x-admin.confirm>
            </x-admin.card>
        </div>
    </div>
@endsection
