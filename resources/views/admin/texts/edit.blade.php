{{--
    Editor of one translation group (docs/CMS.md §7.3, §13 F27), rendered by Admin\TextController@edit
    (and again with HTTP 422 by @update when a text is refused: the submitted values are in the rows).
    Vars: $group, $label, $text (?string), $sections (list of ['key', 'title', 'rows']), $total,
    $modified (rows changed), $locales, $view (?string public URL), $slots (slot partial vars), $metaLength.

    Inputs: t[{locale}][{dot.key}] and reset[{locale}][{dot.key}] (the dots stay inside the brackets).
    js/admin/text-editor.js: live "modifié" badges and counter, original text, reset checkboxes,
    "Seulement les textes modifiés"; the search box is admin.js's [data-adm-filter].
--}}
@extends('admin.layouts.app')

@section('title', __('admin_content.texts.edit_title', ['group' => $label]))

@if ($view)
    @section('site_url', $view)
@endif

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/content.css') }}">
@endpush

@if ($slots !== [])
    @include('admin.texts.partials.picker-assets')
@endif

@push('scripts')
    <script src="{{ ap_asset('js/admin/text-editor.js') }}" defer></script>
@endpush

@section('content')
    <x-admin.page-head :title="__('admin_content.texts.edit_title', ['group' => $label])" :lead="$text ?? __('admin_content.texts.edit_lead')"
                       :back="route('admin.texts.index')">
        @if ($view)
            <a class="btn btn--sm btn--ghost" href="{{ $view }}" target="_blank" rel="noopener">
                <x-icon name="external" />
                <span class="btn__label">{{ __('admin_content.texts.view') }}</span>
                <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
            </a>
        @endif
    </x-admin.page-head>

    @if ($slots !== [])
        <x-admin.card :title="__('admin_content.texts.spots')" accent="blue" class="adm-text-spots">
            <p class="adm-muted adm-small">{{ __('admin_content.texts.spots_lead') }}</p>
            <div class="adm-text-spots__list">
                @foreach ($slots as $slot)
                    @include('admin.media.partials.slot', $slot)
                @endforeach
            </div>
        </x-admin.card>
    @endif

    @if ($errors->any())
        <p class="adm-flash adm-flash--info adm-text-nothing-saved" role="status">
            <span class="adm-flash__icon" aria-hidden="true"><x-admin.icon name="info" /></span>
            <span class="adm-flash__text">{{ __('admin_content.texts.errors.summary') }}</span>
        </p>
    @endif

    <form class="adm-form adm-texts" method="POST" action="{{ route('admin.texts.update', $group) }}"
          data-adm-dirty data-adm-busy data-text-editor>
        @csrf
        @method('PUT')

        <div class="adm-texts__toolbar">
            <label class="adm-search">
                <span class="visually-hidden">{{ __('admin_content.texts.filter') }}</span>
                <x-admin.icon name="search" />
                <input class="field__input" type="search" placeholder="{{ __('admin_content.texts.filter_placeholder') }}"
                       data-adm-filter="#texts-list" data-adm-filter-count="#texts-results" data-adm-filter-empty="#texts-empty"
                       data-adm-no-draft>
            </label>
            <label class="adm-texts__only" hidden data-text-only>
                <input type="checkbox" data-adm-no-draft>
                <span>{{ __('admin_content.texts.only_modified') }}</span>
            </label>
            <p class="adm-texts__counter" aria-live="polite" data-text-counter
               data-zero="{{ trans_choice('admin_content.texts.modified', 0, ['count' => 0]) }}"
               data-one="{{ trans_choice('admin_content.texts.modified', 1, ['count' => 1]) }}"
               data-many="{{ trans_choice('admin_content.texts.modified', 2, ['count' => ':count']) }}">
                <span class="adm-texts__counter-dot" aria-hidden="true"></span>
                <span data-text-counter-text>{{ trans_choice('admin_content.texts.modified', $modified, ['count' => $modified]) }}</span>
                <span class="adm-texts__total">/ {{ trans_choice('admin_content.texts.count', $total, ['count' => $total]) }}</span>
            </p>
        </div>
        <p class="adm-muted adm-small adm-texts__results" id="texts-results" aria-live="polite"></p>

        <div class="adm-texts__list" id="texts-list">
            @forelse ($sections as $section)
                <fieldset class="adm-fieldset adm-texts__section" data-adm-filter-group data-text-section>
                    <legend>{{ $section['title'] }}</legend>
                    @foreach ($section['rows'] as $row)
                        @include('admin.texts.partials.row', ['row' => $row, 'metaLength' => $metaLength])
                    @endforeach
                </fieldset>
            @empty
                <x-admin.empty :title="__('admin_content.texts.none')" icon="text" />
            @endforelse
            <x-admin.empty :title="__('admin_content.texts.no_results')" icon="search" id="texts-empty" hidden />
        </div>

        <x-admin.savebar :back="route('admin.texts.index')">{{ __('admin_content.texts.empty_note') }}</x-admin.savebar>
    </form>
@endsection
