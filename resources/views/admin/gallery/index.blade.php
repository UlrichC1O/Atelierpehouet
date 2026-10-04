{{--
    The public gallery's photos in display order (docs/CMS.md §7.6): uploader preset in_gallery = 1, a
    sortable grid (sortable.js saves the order at once as JSON; without JavaScript the "Monter /
    Descendre" buttons and "Enregistrer l'ordre" submit the form) and "Retirer de la galerie" buttons
    (one small form per photo, outside the order form: forms never nest).
    Vars (Admin\GalleryController::index): $photos — list of [media, item, name, service].
--}}
@extends('admin.layouts.app')

@section('title', __('admin_media.gallery.title'))
@section('site_url', route('gallery'))

@pushOnce('styles', 'admin-media-css')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/media.css') }}">
@endPushOnce

@pushOnce('scripts', 'admin-sortable-js')
    <script src="{{ ap_asset('js/admin/sortable.js') }}" defer></script>
@endPushOnce

@section('content')
    <x-admin.page-head :title="__('admin_media.gallery.title')" :lead="__('admin_media.gallery.lead')">
        <a class="btn btn--sm btn--ghost" href="{{ route('admin.media.index') }}">
            <x-admin.icon name="image" />
            <span class="btn__label">{{ __('admin_media.gallery.library') }}</span>
        </a>
        <a class="btn btn--sm btn--secondary" href="{{ route('gallery') }}" target="_blank" rel="noopener">
            <x-icon name="external" />
            <span class="btn__label">{{ __('admin_media.gallery.view') }}</span>
            <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
        </a>
    </x-admin.page-head>

    <x-admin.card :title="__('admin_media.gallery.upload_title')" accent="amber">
        @include('admin.media.partials.uploader', [
            'defaults' => ['in_gallery' => 1],
            'multiple' => true,
            'redirect' => route('admin.gallery.index'),
            'label' => __('admin_media.gallery.upload_title'),
        ])
    </x-admin.card>

    @if ($photos)
        <section class="adm-stack adm-gallery" aria-labelledby="gallery-order-title">
            <div class="adm-gallery__head">
                <h2 class="adm-gallery__title" id="gallery-order-title">{{ trans_choice('admin_media.gallery.count', count($photos), ['count' => count($photos)]) }}</h2>
                <p class="field__hint" id="gallery-order-hint">{{ __('admin_media.gallery.order_hint') }}</p>
            </div>

            <form class="adm-gallery__form" id="gallery-order" method="POST" action="{{ route('admin.gallery.reorder') }}">
                @csrf
                <ol class="adm-sortable adm-gallery__grid" data-sortable data-sortable-autosubmit
                    aria-label="{{ __('admin_media.gallery.order_label') }}" aria-describedby="gallery-order-hint">
                    @foreach ($photos as $photo)
                        @php($item = $photo['item'])
                        <li class="adm-sortable__item adm-gallery__item" data-sortable-item data-sortable-value="{{ $item->id }}">
                            <input type="hidden" name="order[]" value="{{ $item->id }}">
                            <span class="adm-gallery__media">
                                <img class="adm-gallery__img" src="{{ $item->url(480) }}" alt="" width="{{ $item->width }}" height="{{ $item->height }}"
                                     style="object-position: {{ $item->objectPosition() }}" loading="lazy" decoding="async" draggable="false">
                                <span class="adm-sortable__number adm-gallery__number" data-sortable-number>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </span>
                            <span class="adm-gallery__body">
                                <span class="adm-gallery__name" title="{{ $photo['name'] }}">{{ $photo['name'] }}</span>
                                @if ($photo['service'])
                                    <x-admin.badge variant="info">{{ $photo['service'] }}</x-admin.badge>
                                @endif
                            </span>
                            <span class="adm-gallery__tools">
                                <button class="adm-handle" type="button" data-sortable-handle>
                                    <x-admin.icon name="drag" />
                                    <span class="visually-hidden">{{ __('admin_media.gallery.move', ['name' => $photo['name']]) }}</span>
                                </button>
                                <span class="adm-sortable__moves">
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $item->id }}:up" data-move="up" @if ($loop->first) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-up" />
                                        <span class="visually-hidden">{{ __('admin_media.gallery.move_up', ['name' => $photo['name']]) }}</span>
                                    </button>
                                    <button class="adm-icon-btn adm-icon-btn--sm" type="submit" name="move" value="{{ $item->id }}:down" data-move="down" @if ($loop->last) aria-disabled="true" @endif>
                                        <x-admin.icon name="move-down" />
                                        <span class="visually-hidden">{{ __('admin_media.gallery.move_down', ['name' => $photo['name']]) }}</span>
                                    </button>
                                </span>
                                <a class="adm-icon-btn adm-icon-btn--sm adm-gallery__edit" href="{{ route('admin.media.edit', $item->id) }}">
                                    <x-admin.icon name="edit" />
                                    <span class="visually-hidden">{{ __('admin_media.card.edit_label', ['name' => $photo['name']]) }}</span>
                                </a>
                            </span>
                            <span class="adm-gallery__foot">
                                <button class="btn btn--sm btn--link adm-gallery__remove" type="submit" form="gallery-remove-{{ $item->id }}"
                                        aria-label="{{ __('admin_media.gallery.remove_label', ['name' => $photo['name']]) }}">
                                    <x-admin.icon name="eye-off" />
                                    <span class="btn__label">{{ __('admin_media.gallery.remove') }}</span>
                                </button>
                            </span>
                        </li>
                    @endforeach
                </ol>
                <noscript>
                    <div class="adm-gallery__save">
                        <button class="btn btn--sm" type="submit">{{ __('admin_media.gallery.save_order') }}</button>
                    </div>
                </noscript>
            </form>

            @foreach ($photos as $photo)
                <form id="gallery-remove-{{ $photo['item']->id }}" method="POST" action="{{ route('admin.gallery.toggle', $photo['item']->id) }}" hidden>
                    @csrf
                    <input type="hidden" name="in_gallery" value="0">
                    <input type="hidden" name="redirect" value="{{ route('admin.gallery.index') }}">
                </form>
            @endforeach

            <p class="adm-gallery__note">{{ __('admin_media.gallery.remove_note') }}</p>
        </section>
    @else
        <x-admin.empty :title="__('admin_media.gallery.empty_title')" :text="__('admin_media.gallery.empty_text')" icon="gallery">
            <a class="btn btn--sm btn--ghost" href="{{ route('admin.media.index') }}">{{ __('admin_media.gallery.library') }}</a>
        </x-admin.empty>
    @endif
@endsection
