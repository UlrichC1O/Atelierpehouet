{{--
    Photo library (docs/CMS.md §7.6, §13 C14): uploader with the destination choice, filters, search,
    grid of 48 photos per page. ?slot=…&redirect=… (photo spot chosen without JavaScript): every card
    gets "Utiliser ici" and a new photo is placed in that spot directly.
--}}
@extends('admin.layouts.app')

@section('title', __('admin_media.library.title'))

@php
    $mode = $slot ? 'slot' : 'library';
@endphp

@pushOnce('styles', 'admin-media-css')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/media.css') }}">
@endPushOnce

@pushOnce('scripts', 'admin-media-picker-js')
    <script src="{{ ap_asset('js/admin/media-picker.js') }}" defer></script>
@endPushOnce

@section('content')
    <x-admin.page-head :title="__('admin_media.library.title')" :lead="__('admin_media.library.lead')">
        <a class="btn btn--sm btn--ghost" href="{{ route('admin.gallery.index') }}">
            <x-admin.icon name="gallery" />
            <span class="btn__label">{{ __('admin_media.library.gallery_link') }}</span>
        </a>
    </x-admin.page-head>

    @if ($viewUrl)
        <p class="adm-upload-notice adm-upload-notice--page">
            <a class="btn btn--sm btn--secondary" href="{{ $viewUrl }}" target="_blank" rel="noopener">
                <x-icon name="external" />
                <span class="btn__label">{{ __('admin_media.uploaded.view') }}</span>
                <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
            </a>
        </p>
    @endif

    @if ($slot)
        <div class="adm-slot-mode" role="note">
            <span class="adm-slot-mode__mark" aria-hidden="true"></span>
            <div class="adm-slot-mode__text">
                <p class="adm-slot-mode__title">{{ __('admin_media.library.slot_mode.title', ['slot' => $slotLabel]) }}</p>
                <p>{{ __('admin_media.library.slot_mode.text') }}</p>
            </div>
            <a class="btn btn--sm btn--ghost" href="{{ $redirect }}">{{ __('admin_media.library.slot_mode.cancel') }}</a>
        </div>
    @endif

    <x-admin.card :title="$slot ? __('admin.uploader.title_one') : __('admin_media.library.upload_title')" accent="blue" class="adm-library-upload">
        @if ($slot)
            @include('admin.media.partials.uploader', [
                'defaults' => ['in_gallery' => 0, 'slot' => $slot],
                'multiple' => false,
                'redirect' => $redirect,
            ])
        @else
            @include('admin.media.partials.uploader', [
                'multiple' => true,
                'destination' => true,
                'services' => $services,
                'redirect' => route('admin.media.index'),
            ])
        @endif
    </x-admin.card>

    @include('admin.media.partials.library', ['mode' => $mode])
@endsection
