{{--
    Admin · one artist's profile (docs/ARTISTS.md §6.2): identity, portrait, presentation (statement,
    Markdown biography, meta description — FR | EN) and publication in one form (PUT), then the danger zone.
    View data: $artist (App\Models\Artist with artworks_count / exhibitions_count), $portrait (?App\Cms\MediaItem),
    $mediaOptions (id ⇒ label), $accents (list<string>).
--}}
@extends('admin.layouts.app')

@section('title', $artist->name)
@section('site_url', $artist->url())

@php
    $baseUrl = rtrim(\Illuminate\Support\Facades\Route::has('artists.index') ? route('artists.index') : url('artistes'), '/').'/';
    $artworkCount = (int) ($artist->artworks_count ?? 0);
    $exhibitionCount = (int) ($artist->exhibitions_count ?? 0);
@endphp

@section('content')
    @include('admin.artists.partials.assets')

    <x-admin.page-head :title="$artist->name" :lead="__('admin_artists.edit.lead')" :back="route('admin.artists.index')" />

    @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'profile'])

    @if ($artist->is_example)
        <div class="alert adm-artist-example" role="note">
            <span class="adm-artist-example__mark" aria-hidden="true"><x-icon name="sparkle" /></span>
            <div class="adm-artist-example__body">
                <p class="adm-artist-example__title">{{ __('admin_artists.edit.example.title') }}</p>
                <p>{{ __('admin_artists.edit.example.text', ['name' => $artist->name]) }}</p>
                <p class="adm-artist-example__actions">
                    <a class="adm-link" href="{{ $artist->url() }}" target="_blank" rel="noopener">{{ __('admin_artists.edit.example.preview') }}<span class="visually-hidden"> ({{ __('admin.a11y.new_tab') }})</span></a>
                    <a class="adm-link" href="#adm-artist-danger">{{ __('admin_artists.edit.example.delete') }}</a>
                </p>
            </div>
        </div>
    @endif

    {{-- The portrait is outside the profile form: each of its actions applies at once (admin.artists.portrait). --}}
    <x-admin.card :title="__('admin_artists.edit.sections.portrait')" id="adm-artist-portrait" class="adm-artist-portrait">
        <p class="field__hint adm-artist-portrait__note">{{ __('admin_artists.fields.portrait.immediate') }}</p>

        <div class="adm-artist-portrait__upload">
            @include('admin.media.partials.uploader', [
                'action' => route('admin.artists.portrait', $artist),
                'multiple' => false,
                'defaults' => [],
                'redirect' => url()->current(),
                'label' => __('admin_artists.fields.portrait.upload'),
            ])
            <p class="field__hint">{{ __('admin_artists.fields.portrait.upload_hint') }}</p>
        </div>

        <form class="adm-form adm-artist-portrait__library" method="POST" action="{{ route('admin.artists.portrait', $artist) }}">
            @csrf
            @include('admin.artists.partials.media-field', [
                'name' => 'media_id',
                'id' => 'artist-portrait-input',
                'label' => __('admin_artists.fields.portrait.library'),
                'media' => $portrait,
                'options' => $mediaOptions,
                'ratio' => '4/5',
                'hint' => __('admin_artists.fields.portrait.hint'),
                'monogram' => $artist->initials(),
                'accent' => $artist->accent,
                'libraryHint' => false,
            ])
            <div class="adm-cluster">
                <button type="submit" class="btn btn--secondary btn--sm">{{ __('admin_artists.fields.portrait.save') }}</button>
            </div>
        </form>
    </x-admin.card>

    <form class="adm-form adm-artist-form" method="POST" action="{{ route('admin.artists.update', $artist) }}" data-adm-dirty>
        @csrf
        @method('PUT')

        <x-admin.card :title="__('admin_artists.edit.sections.identity')">
            <div class="adm-grid adm-grid--2">
                <x-admin.field name="name" :label="__('admin_artists.fields.name.label')" :value="$artist->name"
                               :hint="__('admin_artists.fields.name.hint')" :maxlength="120" required autocomplete="off" />
                <div class="adm-artist-slug">
                    <x-admin.field name="slug" :label="__('admin_artists.fields.slug.label')" :value="$artist->slug"
                                   :hint="__('admin_artists.fields.slug.hint_edit')" :maxlength="80" required autocomplete="off"
                                   autocapitalize="none" spellcheck="false" data-artist-slug />
                    <p class="adm-artist-slug__preview">
                        <span class="adm-artist-slug__label">{{ __('admin_artists.fields.slug.preview') }}</span>
                        <span class="adm-artist-slug__url">{{ $baseUrl }}<strong data-artist-slug-preview data-placeholder="…">{{ old('slug', $artist->slug) ?: '…' }}</strong></span>
                    </p>
                </div>
            </div>

            <div class="adm-pair">
                <x-admin.field name="discipline_fr" :label="__('admin_artists.fields.discipline.label')" lang="fr" :value="$artist->discipline_fr"
                               :hint="__('admin_artists.fields.discipline.hint')" :maxlength="120" />
                <x-admin.field name="discipline_en" :label="__('admin_artists.fields.discipline.label')" lang="en" :value="$artist->discipline_en"
                               :hint="__('admin_artists.fields.en_hint')" :maxlength="120" />
            </div>

            <x-admin.field name="location" :label="__('admin_artists.fields.location.label')" :value="$artist->location"
                           :hint="__('admin_artists.fields.location.hint')" :maxlength="120" />

            @include('admin.artists.partials.accent-field', ['accents' => $accents, 'value' => $artist->accent])

            <div class="adm-grid adm-grid--2">
                <x-admin.field name="website" type="url" :label="__('admin_artists.fields.website.label')" :value="$artist->website"
                               :hint="__('admin_artists.fields.website.hint')" :maxlength="255" placeholder="https://" inputmode="url" autocomplete="url" />
                <x-admin.field name="instagram" type="url" :label="__('admin_artists.fields.instagram.label')" :value="$artist->instagram"
                               :hint="__('admin_artists.fields.instagram.hint')" :maxlength="255" placeholder="https://www.instagram.com/" inputmode="url" />
            </div>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.edit.sections.presentation')">
            <div class="adm-pair">
                <x-admin.field name="statement_fr" type="textarea" :rows="3" lang="fr" :label="__('admin_artists.fields.statement.label')" :value="$artist->statement_fr"
                               :hint="__('admin_artists.fields.statement.hint')" :maxlength="400" />
                <x-admin.field name="statement_en" type="textarea" :rows="3" lang="en" :label="__('admin_artists.fields.statement.label')" :value="$artist->statement_en"
                               :hint="__('admin_artists.fields.en_hint')" :maxlength="400" />
            </div>

            <div class="adm-pair">
                <x-admin.field name="bio_fr" type="textarea" :rows="14" lang="fr" :label="__('admin_artists.fields.bio.label')" :value="$artist->bio_fr"
                               :hint="__('admin_artists.fields.bio.hint')" :maxlength="10000" />
                <x-admin.field name="bio_en" type="textarea" :rows="14" lang="en" :label="__('admin_artists.fields.bio.label')" :value="$artist->bio_en"
                               :hint="__('admin_artists.fields.en_hint')" :maxlength="10000" />
            </div>

            @include('admin.artists.partials.markdown-help')

            <div class="adm-pair">
                <x-admin.field name="meta_fr" type="textarea" :rows="2" lang="fr" :label="__('admin_artists.fields.meta.label')" :value="$artist->meta_fr"
                               :hint="__('admin_artists.fields.meta.hint')" :maxlength="170" />
                <x-admin.field name="meta_en" type="textarea" :rows="2" lang="en" :label="__('admin_artists.fields.meta.label')" :value="$artist->meta_en"
                               :hint="__('admin_artists.fields.en_hint')" :maxlength="170" />
            </div>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.edit.sections.publication')">
            <x-admin.toggle name="is_published" :label="__('admin_artists.fields.is_published.label')" :checked="(bool) $artist->is_published"
                            :hint="__('admin_artists.fields.is_published.hint')" />
        </x-admin.card>

        <x-admin.savebar :back="route('admin.artists.index')" />
    </form>

    <x-admin.card :title="__('admin_artists.edit.sections.danger')" accent="red" class="adm-artist-danger" id="adm-artist-danger">
        <p class="adm-artist-danger__text">{{ __('admin_artists.edit.delete.text', ['works' => $artworkCount, 'exhibitions' => $exhibitionCount]) }}</p>
        <x-admin.confirm :action="route('admin.artists.destroy', $artist)" :label="__('admin_artists.edit.delete.button')"
                         :message="__('admin_artists.edit.delete.confirm', ['name' => $artist->name])" class="adm-artist-danger__form">
            <label class="checkbox adm-artist-danger__check">
                <input type="checkbox" name="delete_photos" value="1">
                <span>{{ __('admin_artists.edit.delete.photos') }}</span>
            </label>
        </x-admin.confirm>
    </x-admin.card>
@endsection
