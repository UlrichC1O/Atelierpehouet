{{--
    Admin · create or edit an exhibition (docs/ARTISTS.md §6.2): titles FR | EN, kind, venue, city, year and
    optional dates (artists.js copies the start date's year into the year), presentation, "find out more"
    link, visual (library photo, 16:10), visibility; delete on edit.
    View data: $artist (with counts), $exhibition (new: kind "group", this year, published), $media
    (?App\Cms\MediaItem), $mediaOptions (id ⇒ label), $kinds (list<string>).
--}}
@extends('admin.layouts.app')

@php
    $isNew = ! $exhibition->exists;
    $expoTitle = $isNew ? __('admin_artists.exhibitions.create.title') : ($exhibition->text('title') ?? __('admin_artists.exhibitions.create.title'));
    $lead = $isNew ? __('admin_artists.exhibitions.create.lead', ['name' => $artist->name]) : __('admin_artists.exhibitions.edit.lead', ['name' => $artist->name]);
    $kindOptions = [];
    foreach ($kinds as $kindKey) {
        $kindOptions[$kindKey] = __('admin_artists.kinds.'.$kindKey);
    }
    $lastYear = (int) now()->year + 10;
@endphp

@section('title', $expoTitle)
@section('site_url', $artist->url())

@section('content')
    @include('admin.artists.partials.assets')

    <x-admin.page-head :title="$expoTitle" :lead="$lead" :back="route('admin.artists.exhibitions.index', $artist)" />

    @include('admin.artists.partials.subnav', ['artist' => $artist, 'current' => 'exhibitions'])

    <form class="adm-form adm-artist-form" method="POST" data-adm-dirty
          action="{{ $isNew ? route('admin.artists.exhibitions.store', $artist) : route('admin.artists.exhibitions.update', [$artist, $exhibition]) }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <x-admin.card :title="__('admin_artists.exhibitions.sections.about')">
            <div class="adm-pair">
                <x-admin.field name="title_fr" lang="fr" :label="__('admin_artists.exhibitions.fields.title.label')" :value="$exhibition->title_fr"
                               :hint="__('admin_artists.exhibitions.fields.title.hint')" :maxlength="160" required />
                <x-admin.field name="title_en" lang="en" :label="__('admin_artists.exhibitions.fields.title.label')" :value="$exhibition->title_en"
                               :hint="__('admin_artists.fields.en_hint')" :maxlength="160" />
            </div>

            <div class="adm-grid adm-grid--3">
                <x-admin.field name="kind" type="select" :label="__('admin_artists.exhibitions.fields.kind.label')" :value="$exhibition->kind ?: 'group'"
                               :options="$kindOptions" :hint="__('admin_artists.exhibitions.fields.kind.hint')" />
                <x-admin.field name="venue" :label="__('admin_artists.exhibitions.fields.venue.label')" :value="$exhibition->venue"
                               :hint="__('admin_artists.exhibitions.fields.venue.hint')" :maxlength="160" />
                <x-admin.field name="city" :label="__('admin_artists.exhibitions.fields.city.label')" :value="$exhibition->city"
                               :hint="__('admin_artists.exhibitions.fields.city.hint')" :maxlength="120" />
            </div>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.exhibitions.sections.dates')">
            <p class="adm-artist-card-intro">{{ __('admin_artists.exhibitions.dates_hint') }}</p>
            <div class="adm-grid adm-grid--3">
                @include('admin.artists.partials.date-field', [
                    'name' => 'starts_on',
                    'label' => __('admin_artists.exhibitions.fields.starts_on.label'),
                    'value' => $exhibition->starts_on?->format('Y-m-d'),
                    'hint' => __('admin_artists.exhibitions.fields.starts_on.hint'),
                    'hook' => 'data-exhibition-start',
                    'min' => '1900-01-01',
                    'max' => $lastYear.'-12-31',
                ])
                @include('admin.artists.partials.date-field', [
                    'name' => 'ends_on',
                    'label' => __('admin_artists.exhibitions.fields.ends_on.label'),
                    'value' => $exhibition->ends_on?->format('Y-m-d'),
                    'hint' => __('admin_artists.exhibitions.fields.ends_on.hint'),
                    'hook' => 'data-exhibition-end',
                    'min' => $exhibition->starts_on?->format('Y-m-d') ?? '1900-01-01',
                ])
                <x-admin.field name="year" type="number" :label="__('admin_artists.exhibitions.fields.year.label')" :value="$exhibition->year"
                               :hint="__('admin_artists.exhibitions.fields.year.hint')" min="1900" :max="$lastYear" step="1" inputmode="numeric"
                               data-exhibition-year />
            </div>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.exhibitions.sections.presentation')">
            <div class="adm-pair">
                <x-admin.field name="description_fr" type="textarea" :rows="4" lang="fr" :label="__('admin_artists.exhibitions.fields.description.label')"
                               :value="$exhibition->description_fr" :hint="__('admin_artists.exhibitions.fields.description.hint')" :maxlength="1500" />
                <x-admin.field name="description_en" type="textarea" :rows="4" lang="en" :label="__('admin_artists.exhibitions.fields.description.label')"
                               :value="$exhibition->description_en" :hint="__('admin_artists.fields.en_hint')" :maxlength="1500" />
            </div>
            <x-admin.field name="url" type="url" :label="__('admin_artists.exhibitions.fields.url.label')" :value="$exhibition->url"
                           :hint="__('admin_artists.exhibitions.fields.url.hint')" :maxlength="255" placeholder="https://" inputmode="url" />
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.exhibitions.sections.visual')">
            @include('admin.artists.partials.media-field', [
                'name' => 'media_id',
                'id' => 'exhibition-media-input',
                'label' => __('admin_artists.exhibitions.fields.visual.label'),
                'media' => $media,
                'options' => $mediaOptions,
                'ratio' => '16/10',
                'hint' => __('admin_artists.exhibitions.fields.visual.hint'),
                'legendHidden' => true,
                'libraryHint' => false,
            ])
            <p class="field__hint">
                @if ($isNew)
                    {{ __('admin_artists.exhibitions.fields.visual.upload_after') }}
                @else
                    <a class="adm-link" href="#adm-artist-visual-upload">{{ __('admin_artists.exhibitions.fields.visual.upload') }}</a>
                @endif
            </p>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.exhibitions.sections.publication')">
            <x-admin.toggle name="is_published" :label="__('admin_artists.exhibitions.fields.is_published.label')" :checked="(bool) ($exhibition->is_published ?? true)"
                            :hint="__('admin_artists.exhibitions.fields.is_published.hint')" />
        </x-admin.card>

        <x-admin.savebar :label="$isNew ? __('admin_artists.exhibitions.create.submit') : null" :back="route('admin.artists.exhibitions.index', $artist)" />
    </form>

    @unless ($isNew)
        {{-- A new visual from the computer or the phone, applied at once (admin.artists.exhibitions.image). --}}
        <x-admin.card :title="__('admin_artists.exhibitions.fields.visual.upload')" id="adm-artist-visual-upload" class="adm-artist-visual-upload">
            @include('admin.media.partials.uploader', [
                'action' => route('admin.artists.exhibitions.image', [$artist, $exhibition]),
                'multiple' => false,
                'defaults' => [],
                'redirect' => url()->current(),
                'label' => __('admin_artists.exhibitions.fields.visual.upload'),
            ])
            <p class="field__hint">{{ __('admin_artists.exhibitions.fields.visual.upload_hint') }}</p>
        </x-admin.card>

        <x-admin.card :title="__('admin_artists.exhibitions.sections.danger')" accent="red" class="adm-artist-danger" id="adm-artist-danger">
            <p class="adm-artist-danger__text">{{ __('admin_artists.exhibitions.delete.text') }}</p>
            <x-admin.confirm :action="route('admin.artists.exhibitions.destroy', [$artist, $exhibition])" :label="__('admin_artists.exhibitions.delete.button')"
                             :message="__('admin_artists.exhibitions.delete.confirm', ['title' => $expoTitle])" class="adm-artist-danger__form" />
        </x-admin.card>
    @endunless
@endsection
