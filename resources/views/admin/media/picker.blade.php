{{--
    The picker's content (GET admin.media.index?picker=1, docs/CMS.md §7.6): no layout — media-picker.js
    puts this HTML into its <dialog> and loads filters, search and pages inside it. Each card has
    "Choisir" ([data-media-choose] + data-media JSON). The uploader (one photo, not in the gallery)
    reuses uploader.js; the photo it adds is chosen at once ("ap:media-uploaded").
    Without JavaScript nobody reaches this partial: the slot partial links to the full library instead.
--}}
<div class="adm-picker" data-picker-root
     data-picker-title="{{ __('admin_media.picker.title') }}"
     data-picker-chosen="{{ __('admin_media.picker.chosen') }}"
     data-picker-cleared="{{ __('admin_media.picker.cleared') }}">
    <details class="adm-picker__upload">
        <summary class="adm-picker__upload-toggle">
            <x-admin.icon name="upload" />
            <span>{{ __('admin_media.picker.upload') }}</span>
        </summary>
        <div class="adm-picker__upload-body">
            <p class="field__hint">{{ __('admin_media.picker.upload_hint') }}</p>
            @include('admin.media.partials.uploader', [
                'defaults' => ['in_gallery' => 0],
                'multiple' => false,
                'redirect' => $opener,
                'label' => __('admin_media.picker.upload'),
            ])
        </div>
    </details>

    @include('admin.media.partials.library', ['mode' => 'picker'])
</div>
