{{--
    Create / edit a free page (docs/CMS.md §7.4, §13 F28), rendered by Admin\PageController (create,
    edit; again with HTTP 422 when invalid; with HTTP 200 and $previewing for "Mettre à jour l'aperçu").
    Vars: $page (CustomPage, new or saved), $photos (list<MediaItem>, the library for the cover
    <select>), $cover (?MediaItem), $previewing (bool), $rendered (['fr' => HtmlString, 'en' => HtmlString]:
    App\Cms\Markdown output), $publicUrl (?string), $siteRoot (string, ends with "/").

    js/admin/text-editor.js (page part): slug suggested from the French title, "Insérer une photo"
    (picker field mode on #page-insert-media ⇒ ![alt](/media/{key}) at the cursor), cover field (picker
    field mode on #page-cover-input, <select> without the picker), unsaved state after a preview.
--}}
@extends('admin.layouts.app')

@php
    $isNew = ! $page->exists;
    $title = $isNew ? __('admin_content.pages.create_title') : __('admin_content.pages.edit_title', ['title' => $page->title_fr]);
@endphp

@section('title', $title)

@if ($publicUrl)
    @section('site_url', $publicUrl)
@endif

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/content.css') }}">
@endpush

@include('admin.texts.partials.picker-assets')

@push('scripts')
    <script src="{{ ap_asset('js/admin/text-editor.js') }}" defer></script>
@endpush

@section('content')
    <x-admin.page-head :title="$title" :lead="$isNew ? __('admin_content.pages.create_lead') : __('admin_content.pages.edit_lead')"
                       :back="route('admin.pages.index')">
        @if ($publicUrl)
            <a class="btn btn--sm btn--ghost" href="{{ $publicUrl }}" target="_blank" rel="noopener">
                <x-icon name="external" />
                <span class="btn__label">{{ $page->is_published ? __('admin_content.pages.view') : __('admin_content.pages.preview_page') }}</span>
                <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
            </a>
        @endif
        @unless ($isNew)
            <x-admin.confirm :action="route('admin.pages.destroy', $page)" :label="__('admin_content.pages.delete')"
                             :message="__('admin_content.pages.delete_confirm', ['title' => $page->title_fr])" />
        @endunless
    </x-admin.page-head>

    @if ($previewing)
        <div class="adm-flash adm-flash--info adm-page-previewing" role="status">
            <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon name="eye" /></span>
            <p class="adm-flash__text">{{ __('admin_content.pages.preview_notice') }}</p>
        </div>
    @endif

    <form class="adm-form adm-page-form" method="POST" action="{{ $isNew ? route('admin.pages.store') : route('admin.pages.update', $page) }}"
          data-adm-dirty data-adm-busy data-page-form data-page-inserted="{{ __('admin_content.pages.inserted') }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless
        {{-- Enter in a field saves (the first submit button of a form is its default one). --}}
        <button class="visually-hidden" type="submit" tabindex="-1" aria-hidden="true">{{ __('admin.actions.save') }}</button>
        @if ($previewing)
            {{-- Changed by text-editor.js once admin.js has read the form: the previewed values count as unsaved. --}}
            <input type="hidden" name="_previewed" value="1" data-page-previewed>
        @endif

        <div class="adm-page-form__layout">
            <div class="adm-stack adm-page-form__main">
                <x-admin.card :title="__('admin_content.pages.cards.main')" accent="orange">
                    <div class="adm-grid adm-grid--2">
                        <x-admin.field name="title_fr" lang="fr" :label="__('admin_content.pages.fields.title_fr')" :value="$page->title_fr"
                                       :maxlength="120" required data-slug-source />
                        <x-admin.field name="title_en" lang="en" :label="__('admin_content.pages.fields.title_en')" :value="$page->title_en"
                                       :maxlength="120" :hint="__('admin_content.pages.fields.title_en_hint')" />
                    </div>
                    <div class="adm-page-slug">
                        <x-admin.field name="slug" :label="__('admin_content.pages.fields.slug')" :value="$page->slug" :maxlength="80"
                                       :hint="__('admin_content.pages.fields.slug_hint').($page->exists && $page->is_published ? ' '.__('admin_content.pages.fields.slug_change') : '')"
                                       autocomplete="off" spellcheck="false" autocapitalize="none" inputmode="url"
                                       data-slug-target :data-slug-auto="$isNew && ! $page->slug" />
                        <p class="adm-page-slug__url" aria-hidden="true"><span>{{ $siteRoot }}</span><strong data-slug-preview>{{ $page->slug }}</strong></p>
                    </div>
                </x-admin.card>

                <x-admin.card :title="__('admin_content.pages.cards.body')" accent="yellow">
                    <div class="adm-page-insert" data-media-field data-page-insert hidden>
                        <input type="hidden" id="page-insert-media" value="" data-page-insert-input>
                        <div class="adm-page-insert__preview" data-media-preview hidden><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" data-media-img></div>
                        <button class="btn btn--sm btn--secondary" type="button" data-media-picker data-media-target="#page-insert-media">
                            <x-admin.icon name="image" />
                            <span class="btn__label">{{ __('admin_content.pages.insert_photo') }}</span>
                        </button>
                        <p class="field__hint">{{ __('admin_content.pages.insert_photo_hint') }}</p>
                    </div>

                    <div class="adm-tabs" data-adm-tabs="page-body">
                        <div class="adm-tabs__list">
                            <button class="adm-tabs__tab" type="button" aria-controls="page-body-fr"><span class="adm-lang adm-lang--fr" aria-hidden="true">FR</span>{{ __('admin.common.french') }}</button>
                            <button class="adm-tabs__tab" type="button" aria-controls="page-body-en"><span class="adm-lang adm-lang--en" aria-hidden="true">EN</span>{{ __('admin.common.english') }}</button>
                        </div>
                        @foreach (['fr', 'en'] as $locale)
                            <section class="adm-tabs__panel adm-stack" id="page-body-{{ $locale }}">
                                <h3 class="adm-tabs__heading">{{ __('admin.common.'.($locale === 'fr' ? 'french' : 'english')) }}</h3>
                                <x-admin.field :name="'body_'.$locale" type="textarea" :lang="$locale" :label="__('admin_content.pages.fields.body_'.$locale)"
                                               :value="$page->getAttribute('body_'.$locale)" :rows="14" :maxlength="\App\Http\Controllers\Admin\PageController::MAX_BODY"
                                               :hint="$locale === 'fr' ? __('admin_content.pages.fields.body_hint') : __('admin_content.pages.fields.body_en_hint')"
                                               class="adm-page-body" data-page-body spellcheck="true" />
                                <x-admin.field :name="'meta_'.$locale" type="textarea" :rows="2" :lang="$locale" :label="__('admin_content.pages.fields.meta_'.$locale)"
                                               :value="$page->getAttribute('meta_'.$locale)" :maxlength="170" :hint="__('admin_content.pages.fields.meta_hint')" />
                            </section>
                        @endforeach
                    </div>

                    @include('admin.pages.partials.markdown-help')
                </x-admin.card>

                <x-admin.card :title="__('admin_content.pages.cards.preview')" accent="blue" id="page-preview">
                    <p class="adm-muted adm-small">{{ __('admin_content.pages.preview_lead') }}</p>
                    <div class="adm-tabs" data-adm-tabs="page-preview">
                        <div class="adm-tabs__list">
                            <button class="adm-tabs__tab" type="button" aria-controls="page-preview-fr"><span class="adm-lang adm-lang--fr" aria-hidden="true">FR</span>{{ __('admin.common.french') }}</button>
                            <button class="adm-tabs__tab" type="button" aria-controls="page-preview-en"><span class="adm-lang adm-lang--en" aria-hidden="true">EN</span>{{ __('admin.common.english') }}</button>
                        </div>
                        @foreach (['fr', 'en'] as $locale)
                            <section class="adm-tabs__panel" id="page-preview-{{ $locale }}">
                                <h3 class="adm-tabs__heading">{{ __('admin.common.'.($locale === 'fr' ? 'french' : 'english')) }}</h3>
                                @if (trim((string) $rendered[$locale]) !== '')
                                    {{-- App\Cms\Markdown output: HTML stripped, unsafe links disabled, library photos only. --}}
                                    <div class="prose adm-page-preview" lang="{{ $locale }}">{!! $rendered[$locale] !!}</div>
                                @else
                                    <p class="adm-muted adm-page-preview adm-page-preview--empty">{{ __('admin_content.pages.preview_empty') }}</p>
                                @endif
                            </section>
                        @endforeach
                    </div>
                    <div class="adm-cluster">
                        <button class="btn btn--sm btn--ghost" type="submit" name="preview" value="1" formnovalidate>
                            <x-admin.icon name="eye" />
                            <span class="btn__label">{{ __('admin_content.pages.preview_button') }}</span>
                        </button>
                    </div>
                </x-admin.card>
            </div>

            <div class="adm-stack adm-page-form__side">
                <x-admin.card :title="__('admin_content.pages.cards.publish')" accent="red">
                    <div class="adm-stack">
                        <x-admin.toggle name="is_published" :label="__('admin_content.pages.fields.is_published')" :checked="(bool) $page->is_published"
                                        :hint="__('admin_content.pages.fields.is_published_hint')" />
                        <x-admin.toggle name="in_footer" :label="__('admin_content.pages.fields.in_footer')" :checked="(bool) $page->in_footer"
                                        :hint="__('admin_content.pages.fields.in_footer_hint')" />
                        <x-admin.field name="position" type="number" :label="__('admin_content.pages.fields.position')" :value="$page->position ?? 0"
                                       min="0" max="65535" step="1" inputmode="numeric" :hint="__('admin_content.pages.fields.position_hint')" />
                    </div>
                </x-admin.card>

                <x-admin.card :title="__('admin_content.pages.cards.cover')" accent="blue">
                    @include('admin.pages.partials.cover-field')
                </x-admin.card>
            </div>
        </div>

        <x-admin.savebar :back="route('admin.pages.index')" />
    </form>
@endsection
