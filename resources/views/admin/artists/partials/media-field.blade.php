{{--
    A photo field of the artist forms (docs/ARTISTS.md §6.2): portrait, artwork photo, exhibition visual.
      @include('admin.artists.partials.media-field', ['name' => 'portrait_media_id', 'id' => 'artist-portrait-input',
               'label' => __('…'), 'media' => $portrait, 'options' => $mediaOptions, 'ratio' => '4/5', 'hint' => __('…')])
    Vars: $name (input name), $id (id of the hidden input the CMS picker fills), $label, $media (?App\Cms\MediaItem,
    the saved photo), $options (id ⇒ label, App\Artists\MediaOptions::list()), $ratio ("4/5", "16/10"… or "natural":
    the photo's own ratio, never cropped), $hint = null, $fit (cover|contain, default: contain for natural),
    $compact (bool: small preview beside the controls), $monogram (?string: initials shown when empty),
    $accent (?string: the artist's accent for the monogram), $legendHidden (bool: the card title already says it),
    $editLink (bool, default true: "Texte alternatif, point focal…" link to the photo's library page),
    $libraryHint (bool, default true: "add the photo to the library first" — false where an upload sits beside the field).

    Without JavaScript: a <select name="{name}"> of the library photos. artists.js (§6.3) keeps the preview in
    sync with it and, when js/admin/media-picker.js is on the page, swaps it for <input type="hidden" id="{id}">
    and shows the [data-media-picker] / [data-media-clear] buttons (CMS picker, field mode, docs/CMS.md §7.6).
    Each <option> carries its preview (data-src, data-ratio, data-pos, data-meta). Error key = the name (field-{name}).
--}}
@php
    $errorKey = trim(str_replace(['[', ']'], ['.', ''], (string) $name), '.');
    $fieldId = 'field-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $errorKey), '-');
    $inputId = (string) ($id ?? $fieldId.'-input');
    $fieldError = isset($errors) ? $errors->first($errorKey) : null;
    $fieldError = $fieldError !== '' ? $fieldError : null;
    $fieldHint = $hint ?? null;
    $compact = (bool) ($compact ?? false);
    $monogram = isset($monogram) && trim((string) $monogram) !== '' ? (string) $monogram : null;
    $fieldAccent = in_array($accent ?? null, (array) config('atelier.accents', []), true) ? $accent : null;
    $legendHidden = (bool) ($legendHidden ?? false);
    $showEditLink = (bool) ($editLink ?? true);

    $saved = is_object($media ?? null) && method_exists($media, 'url') ? $media : null;
    $savedId = $saved ? (int) $saved->id : null;
    $old = old($errorKey);
    $selectedId = is_scalar($old) ? (is_numeric($old) && (int) $old > 0 ? (int) $old : null) : $savedId;
    $preview = $selectedId === $savedId ? $saved : \App\Artists\ArtistDirectory::media($selectedId);

    $natural = ($ratio ?? null) === 'natural';
    $fixedRatio = ! $natural && preg_match('#^\d+(\.\d+)?/\d+(\.\d+)?$#', (string) ($ratio ?? '')) === 1 ? (string) $ratio : '4/3';
    $frameRatio = $natural ? ($preview ? $preview->ratio() : '4 / 3') : str_replace('/', ' / ', $fixedRatio);
    $fit = in_array($fit ?? null, ['cover', 'contain'], true) ? $fit : ($natural ? 'contain' : 'cover');

    // "atelier.jpg · 1600 × 1200 px": the line under the preview.
    $describe = fn ($item): string => implode(' · ', array_filter([$item->originalName, __('admin_artists.media.size', ['width' => $item->width, 'height' => $item->height])]));
    // Every choice carries its preview, so the field can show the photo as soon as it is picked.
    $choices = [];
    foreach ((array) ($options ?? []) as $optionId => $optionLabel) {
        if (! is_numeric($optionId) || (int) $optionId < 1) {
            continue;
        }
        $optionMedia = \App\Artists\ArtistDirectory::media((int) $optionId);
        $choices[(int) $optionId] = [
            'label' => (string) $optionLabel,
            'src' => $optionMedia?->url(480),
            'ratio' => $optionMedia?->ratio(),
            'pos' => $optionMedia?->objectPosition(),
            'meta' => $optionMedia ? $describe($optionMedia) : null,
        ];
    }
    if ($preview && ! isset($choices[(int) $preview->id])) {
        $choices[(int) $preview->id] = [
            'label' => '#'.$preview->id.' · '.($preview->originalName ?: $preview->alt()),
            'src' => $preview->url(480),
            'ratio' => $preview->ratio(),
            'pos' => $preview->objectPosition(),
            'meta' => $describe($preview),
        ];
    }

    // The photo library exists (photos to choose, or its table): else say it is coming.
    $libraryReady = $choices !== [] || $saved !== null
        || (class_exists(\App\Cms\Media\MediaManager::class) && rescue(fn () => \Illuminate\Support\Facades\Schema::hasTable('media'), false, false));
    $editTemplate = $showEditLink && \Illuminate\Support\Facades\Route::has('admin.media.edit') && view()->exists('admin.media.edit') ? route('admin.media.edit', ['media' => 0]) : null;
    $editTemplate = $editTemplate ? preg_replace('#/0$#', '/__ID__', $editTemplate) : null;
    $blankImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    $metaText = $preview ? $describe($preview) : '';
    $describedBy = trim(($fieldHint !== null ? $fieldId.'-hint ' : '').($fieldError ? $fieldId.'-error' : ''));
@endphp
<fieldset @class(['adm-artist-media', 'adm-artist-media--'.$fit, 'adm-artist-media--compact' => $compact, 'accent-'.$fieldAccent => $fieldAccent, 'field--invalid' => $fieldError])
          id="{{ $fieldId }}" data-media-field @if ($natural) data-media-natural @endif
          data-media-selected-label="{{ __('admin_artists.media.selected', ['id' => '__ID__']) }}"
          @if ($editTemplate) data-media-edit-template="{{ $editTemplate }}" @endif>
    <legend @class(['field__label', 'adm-artist-media__legend', 'visually-hidden' => $legendHidden])>{{ $label }}</legend>

    <div class="adm-artist-media__body">
        <div class="adm-artist-media__frame" data-media-preview style="aspect-ratio: {{ $frameRatio }}">
            <img class="adm-artist-media__img" data-media-img decoding="async"
                 src="{{ $preview ? $preview->url(960) : $blankImage }}"
                 @if ($preview) srcset="{{ $preview->srcset() }}" sizes="(min-width: 64em) 22rem, 90vw" style="object-position: {{ $preview->objectPosition() }}" @endif
                 alt="{{ $preview ? __('admin_artists.media.current', ['alt' => $preview->alt()]) : '' }}"
                 @if (! $preview) hidden @endif>
            <div class="adm-artist-media__empty" data-media-empty @if ($preview) hidden @endif>
                @if ($monogram)
                    <span class="adm-artist-media__monogram" aria-hidden="true">{{ $monogram }}</span>
                @else
                    <span class="adm-artist-media__tri" aria-hidden="true"></span>
                @endif
                <span class="adm-artist-media__empty-text">{{ __('admin_artists.media.empty') }}</span>
            </div>
        </div>

        <div class="adm-artist-media__side">
            <p class="adm-artist-media__meta" data-media-meta @if ($metaText === '') hidden @endif>{{ $metaText }}</p>

            <div class="adm-artist-media__fallback" data-media-fallback>
                <label class="adm-artist-media__select-label" for="{{ $inputId }}-select">{{ __('admin_artists.media.select') }}</label>
                <select class="field__input" id="{{ $inputId }}-select" name="{{ $name }}" data-media-select data-media-input-id="{{ $inputId }}"
                        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif @if ($fieldError) aria-invalid="true" @endif>
                    <option value="">{{ __('admin_artists.media.none') }}</option>
                    @foreach ($choices as $choiceId => $choice)
                        <option value="{{ $choiceId }}" @if ($choice['src']) data-src="{{ $choice['src'] }}" data-ratio="{{ $choice['ratio'] }}" data-pos="{{ $choice['pos'] }}" data-meta="{{ $choice['meta'] }}" @endif @selected($choiceId === $selectedId)>{{ $choice['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="adm-artist-media__actions">
                <button class="btn btn--sm btn--secondary" type="button" data-media-picker data-media-target="#{{ $inputId }}" hidden>
                    <x-admin.icon name="image" />
                    <span class="btn__label">{{ __('admin_artists.media.choose') }}</span>
                </button>
                <button class="btn btn--sm btn--ghost" type="button" data-media-clear hidden>
                    <span class="btn__label">{{ __('admin_artists.media.clear') }}</span>
                </button>
                @if ($editTemplate)
                    <a class="adm-artist-media__edit" href="{{ $preview ? str_replace('__ID__', (string) $preview->id, $editTemplate) : '#' }}" data-media-edit @if (! $preview) hidden @endif>
                        <x-admin.icon name="focus" />
                        <span>{{ __('admin_artists.media.edit') }}</span>
                    </a>
                @endif
            </div>

            @if ($fieldHint !== null)
                <p class="field__hint" id="{{ $fieldId }}-hint">{{ $fieldHint }}</p>
            @endif
            @if ($libraryHint ?? true)
                <p class="field__hint adm-artist-media__library" data-media-library-hint>{{ $libraryReady ? __('admin_artists.media.library_hint') : __('admin_artists.media.unavailable') }}</p>
            @endif
            @if ($fieldError)
                <p class="field__error" id="{{ $fieldId }}-error">{{ $fieldError }}</p>
            @endif
        </div>
    </div>
</fieldset>
