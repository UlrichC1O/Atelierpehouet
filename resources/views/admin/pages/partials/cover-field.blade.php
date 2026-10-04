{{--
    Cover photo of a free page (docs/CMS.md §7.4, §7.6 picker field mode).
    Vars (from admin.pages.form): $page, $photos (list<MediaItem>), $cover (?MediaItem).

    Without JavaScript (or without js/admin/media-picker.js): a <select name="cover_media_id"> of the
    library. text-editor.js then swaps it for <input type="hidden" id="page-cover-input"
    name="cover_media_id"> filled by the picker ([data-media-picker][data-media-target]) and shows the
    "Choisir / Retirer" buttons; the preview follows the value either way.
--}}
@php
    $coverError = $errors->first('cover_media_id');
    $coverError = $coverError !== '' ? $coverError : null;
    // A replayed form (invalid or preview) shows the submitted choice, even "no photo".
    $coverOld = session()->hasOldInput() ? old('cover_media_id') : null;
    $coverId = session()->hasOldInput()
        ? (is_numeric($coverOld) && (int) $coverOld > 0 ? (int) $coverOld : null)
        : ($page->cover_media_id ? (int) $page->cover_media_id : null);
    $coverItem = null;
    foreach ($photos as $photo) {
        if ($photo->id === $coverId) {
            $coverItem = $photo;
        }
    }
    $coverItem ??= $coverId === ($cover?->id) ? $cover : null;
    $blankImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    $coverDescribedBy = trim('field-cover_media_id-hint'.($coverError ? ' field-cover_media_id-error' : ''));
@endphp
<div @class(['adm-page-cover', 'field--invalid' => $coverError]) data-media-field data-page-cover>
    <div class="adm-page-cover__frame" data-media-preview>
        <img class="adm-page-cover__img" data-media-img decoding="async" alt=""
             src="{{ $coverItem ? $coverItem->url(960) : $blankImage }}"
             @if ($coverItem) srcset="{{ $coverItem->srcset() }}" sizes="(min-width: 80em) 22rem, 90vw" style="object-position: {{ $coverItem->objectPosition() }}" @endif
             @if (! $coverItem) hidden @endif>
        <div class="adm-page-cover__empty" data-media-empty @if ($coverItem) hidden @endif>
            <span class="adm-page-cover__tri" aria-hidden="true"></span>
            <span>{{ __('admin_content.pages.fields.cover_empty') }}</span>
        </div>
    </div>

    <div class="field adm-page-cover__fallback" data-page-cover-fallback>
        <label class="field__label" for="field-cover_media_id">{{ __('admin_content.pages.fields.cover_select') }}</label>
        <select class="field__input" id="field-cover_media_id" name="cover_media_id" data-page-cover-select
                aria-describedby="{{ $coverDescribedBy }}" @if ($coverError) aria-invalid="true" @endif>
            <option value="">{{ __('admin_content.pages.fields.cover_none') }}</option>
            @foreach ($photos as $photo)
                <option value="{{ $photo->id }}" data-src="{{ $photo->url(480) }}" data-pos="{{ $photo->objectPosition() }}" data-key="{{ $photo->key() }}"
                        data-alt-fr="{{ $photo->alt('fr') }}" data-alt-en="{{ $photo->alt('en') }}"
                        @selected($photo->id === $coverId)>#{{ $photo->id }} · {{ $photo->originalName ?: ($photo->alt() ?: __('admin.common.size', ['width' => $photo->width, 'height' => $photo->height])) }}</option>
            @endforeach
        </select>
    </div>

    <div class="adm-cluster adm-page-cover__actions" data-page-cover-actions hidden>
        <button class="btn btn--sm btn--secondary" type="button" data-media-picker data-media-target="#page-cover-input"
                data-label-choose="{{ __('admin_content.pages.fields.cover_choose') }}" data-label-change="{{ __('admin_content.pages.fields.cover_change') }}">
            <x-admin.icon name="image" />
            <span class="btn__label">{{ $coverItem ? __('admin_content.pages.fields.cover_change') : __('admin_content.pages.fields.cover_choose') }}</span>
        </button>
        <button class="btn btn--sm btn--ghost" type="button" data-media-clear @if (! $coverItem) hidden @endif>
            <span class="btn__label">{{ __('admin_content.pages.fields.cover_clear') }}</span>
        </button>
    </div>

    <p class="field__hint" id="field-cover_media_id-hint">
        {{ __('admin_content.pages.fields.cover_hint') }}
        @if ($photos === [])
            <span data-page-cover-library>{{ __('admin_content.pages.fields.cover_library') }}</span>
        @endif
    </p>
    @if ($coverError)
        <p class="field__error" id="field-cover_media_id-error">{{ $coverError }}</p>
    @endif
</div>
