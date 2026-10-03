{{--
    Admin · Nouvel artiste (docs/ARTISTS.md §6.2): name, page address (filled from the name by artists.js
    until edited by hand), discipline FR | EN, location, accent ⇒ admin.artists.store, then the edit screen.
    View data: $artist (new App\Models\Artist with its accent), $accents (list<string>).
--}}
@extends('admin.layouts.app')

@section('title', __('admin_artists.create.title'))

@php
    $baseUrl = rtrim(\Illuminate\Support\Facades\Route::has('artists.index') ? route('artists.index') : url('artistes'), '/').'/';
    $nextSteps = __('admin_artists.create.next');
    $nextSteps = is_array($nextSteps) ? $nextSteps : [];
@endphp

@section('content')
    @include('admin.artists.partials.assets')

    <x-admin.page-head :title="__('admin_artists.create.title')" :lead="__('admin_artists.create.lead')" :back="route('admin.artists.index')" />

    <form class="adm-form adm-artist-form" method="POST" action="{{ route('admin.artists.store') }}" data-adm-dirty>
        @csrf

        <x-admin.card :title="__('admin_artists.edit.sections.identity')">
            <div class="adm-grid adm-grid--2">
                <x-admin.field name="name" :label="__('admin_artists.fields.name.label')" :value="$artist->name"
                               :hint="__('admin_artists.fields.name.hint')" :maxlength="120" required autocomplete="off" data-artist-slug-source />
                <div class="adm-artist-slug">
                    <x-admin.field name="slug" :label="__('admin_artists.fields.slug.label')" :value="$artist->slug"
                                   :hint="__('admin_artists.fields.slug.hint')" :maxlength="80" autocomplete="off"
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
        </x-admin.card>

        <aside class="adm-artist-next" aria-labelledby="adm-artist-next-title">
            <p class="adm-artist-next__title" id="adm-artist-next-title">{{ __('admin_artists.create.next_title') }}</p>
            <ol class="adm-artist-next__steps" role="list">
                @foreach ($nextSteps as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <p class="adm-artist-next__note">{{ __('admin_artists.create.draft') }}</p>
        </aside>

        <x-admin.savebar :label="__('admin_artists.create.submit')" :back="route('admin.artists.index')" />
    </form>
@endsection
