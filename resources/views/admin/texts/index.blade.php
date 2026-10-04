{{--
    « Textes des pages » (docs/CMS.md §7.3): one card per editable translation group, with its number
    of changed texts, "Modifier les textes", "Voir la page" and the photo spots of that page.
    Rendered by Admin\TextController@index. Vars: $cards (list of ['group', 'label', 'text', 'count',
    'modified', 'url', 'view' (?string), 'slots' (list of slot partial vars)]).
--}}
@extends('admin.layouts.app')

@section('title', __('admin_content.texts.title'))

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/content.css') }}">
@endpush

@if (collect($cards)->contains(fn ($card) => $card['slots'] !== []))
    @include('admin.texts.partials.picker-assets')
@endif

@section('content')
    <x-admin.page-head :title="__('admin_content.texts.title')" :lead="__('admin_content.texts.lead')" />

    <div class="adm-toolbar">
        <label class="adm-search">
            <span class="visually-hidden">{{ __('admin_content.texts.search') }}</span>
            <x-admin.icon name="search" />
            <input class="field__input" type="search" placeholder="{{ __('admin_content.texts.search_placeholder') }}"
                   data-adm-filter="#text-groups" data-adm-filter-empty="#text-groups-empty">
        </label>
    </div>

    @php($accents = ['yellow', 'blue', 'red', 'amber', 'orange', 'white'])
    <div class="adm-grid adm-grid--2 adm-text-groups" id="text-groups">
        @foreach ($cards as $card)
            <x-admin.card :title="$card['label']" :accent="$accents[$loop->index % count($accents)]"
                          class="adm-text-group" id="texts-{{ $card['group'] }}"
                          data-adm-filter-item data-adm-filter-text="{{ $card['label'].' '.$card['text'] }}">
                <div class="adm-stack adm-text-group__body">
                    @if ($card['text'])
                        <p class="adm-muted">{{ $card['text'] }}</p>
                    @endif
                    <p class="adm-text-group__count">
                        <span>{{ trans_choice('admin_content.texts.count', $card['count'], ['count' => $card['count']]) }}</span>
                        @if ($card['modified'] > 0)
                            <x-admin.badge variant="modified">{{ trans_choice('admin_content.texts.modified', $card['modified'], ['count' => $card['modified']]) }}</x-admin.badge>
                        @endif
                    </p>
                    <div class="adm-cluster">
                        <a class="btn btn--sm" href="{{ $card['url'] }}">
                            <x-admin.icon name="edit" />
                            <span class="btn__label">{{ __('admin_content.texts.edit') }}</span>
                            <span class="visually-hidden">— {{ $card['label'] }}</span>
                        </a>
                        @if ($card['view'])
                            <a class="btn btn--sm btn--ghost" href="{{ $card['view'] }}" target="_blank" rel="noopener">
                                <x-icon name="external" />
                                <span class="btn__label">{{ __('admin_content.texts.view') }}</span>
                                <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
                            </a>
                        @endif
                    </div>

                    @if ($card['slots'] !== [])
                        <div class="adm-text-group__spots">
                            <p class="adm-text-group__spots-title">
                                <x-admin.icon name="image" />
                                <span>{{ __('admin_content.texts.spots') }}</span>
                            </p>
                            @foreach ($card['slots'] as $slot)
                                @include('admin.media.partials.slot', $slot)
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-admin.card>
        @endforeach
    </div>

    <x-admin.empty :title="__('admin.empty.search')" icon="search" id="text-groups-empty" hidden />
@endsection
