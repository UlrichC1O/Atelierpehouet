{{--
    Photo uploader (docs/CMS.md §6/§7.6, §13 C10/C13/C14) — markup by admin-ui, owned by admin-media.
      @include('admin.media.partials.uploader', ['defaults' => ['in_gallery' => 1], 'multiple' => true])
    Vars: $action (default route('admin.media.store')), $defaults (hidden fields, e.g. service_slug,
    in_gallery, slot), $multiple (bool, default true), $redirect (default: this page), $label (title).
    Optional (admin-media): $destination (bool, default false: the library's "where do the photos
    appear" choice, docs/CMS.md §13 C14 — radio "destination" gallery|service|library + select
    "service_slug"), $services (slug ⇒ title for that select), $mode ('upload' | 'replace': the label
    of a finished row), $hint (extra sentence under the formats).
    Never place it inside another <form>. The partial pushes css/admin/media.css and js/admin/uploader.js
    once per page (uploader.js ignores a second copy of itself).

    Without JavaScript: a real multipart form posting ONE file ("photo"; `multiple` is only switched on
    by the script). form[data-uploader] carries the client settings from config('cms.media'):
      data-max-edge, data-quality, data-widths (JSON), data-max-kb, data-types (JSON list of MIME types),
      data-multiple ("1"/"0"), data-max-request (bytes per request = MediaManager::maxUploadBytes()),
      data-max-file (largest stored file, MediaManager::MAX_STORED_BYTES),
      data-uploader-i18n (JSON strings of admin_media.uploader), data-uploader-mode
    Inner hooks: [data-uploader-drop] (drop zone, gets .is-dragover / .is-busy on the form),
    input[data-uploader-input] (the file input), [data-uploader-submit] (no-JS submit, hidden by the
    script), [data-uploader-notice] (result of the last batch), ol[data-uploader-list] (.adm-uploads
    status list, aria-live; rows documented in admin.css).
    Events (on the form, bubbling): "ap:media-uploaded" (cancelable, detail {media, response}) after each
    photo — call preventDefault() to handle it without the page reload that otherwise ends the batch —
    and "ap:uploader-done" (detail {uploaded: [responses], failed: count}) at the end of each batch.
--}}
@php
    $action = $action ?? route('admin.media.store');
    $defaults = (array) ($defaults ?? []);
    $multiple = (bool) ($multiple ?? true);
    $redirect = $redirect ?? request()->fullUrl();
    $destination = (bool) ($destination ?? false);
    $mode = ($mode ?? 'upload') === 'replace' ? 'replace' : 'upload';
    $mediaConfig = (array) config('cms.media', []);
    $maxKb = (int) ($mediaConfig['max_kb'] ?? 8192);
    // Effective limit of one request (docs/CMS.md §13 C10): the library's, php.ini's and the host's.
    $maxBytes = (int) rescue(fn () => app(\App\Cms\Media\MediaManager::class)->maxUploadBytes(), $maxKb * 1024, false);
    $maxMb = str_replace('.', app()->getLocale() === 'fr' ? ',' : '.', rtrim(rtrim(number_format($maxBytes / 1048576, 1, '.', ''), '0'), '.'));
    $types = array_keys((array) ($mediaConfig['types'] ?? []));
    // "JPEG, PNG, WebP ou GIF", from the accepted types (the hint never lists a refused format).
    $formatNames = array_values(array_unique(array_map(
        fn ($extension) => ['jpg' => 'JPEG', 'jpeg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WebP', 'gif' => 'GIF', 'avif' => 'AVIF'][strtolower((string) $extension)] ?? strtoupper((string) $extension),
        array_values((array) ($mediaConfig['types'] ?? [])),
    )));
    $lastFormat = array_pop($formatNames);
    $formats = $formatNames ? implode(', ', $formatNames).' '.__('admin.common.or').' '.$lastFormat : (string) $lastFormat;
    $label = $label ?? ($multiple ? __('admin.uploader.title') : __('admin.uploader.title_one'));
    $uploaderId = 'uploader-'.substr(md5($action.'|'.json_encode($defaults).'|'.($destination ? 'd' : '')), 0, 8);

    if ($destination) {
        // The destination choice decides in_gallery / service_slug: the presets would contradict it.
        unset($defaults['in_gallery'], $defaults['service_slug']);
        $services = (array) ($services ?? \App\Http\Controllers\Admin\MediaController::serviceOptions());
        $destinationChoice = old('destination', 'gallery');
    }

    $uploaderStrings = (array) trans('admin_media.uploader');
    $uploaderStrings['too_big'] = __('admin_media.uploader.too_big', ['max' => $maxMb]);
    $uploaderStrings['gif_too_big'] = __('admin_media.uploader.gif_too_big', ['max' => $maxMb]);
    $uploaderStrings['batch'] = (array) trans('admin_media.uploaded.batch');
    $uploaderStrings['view'] = __('admin_media.uploaded.view');
    $uploaderStrings['close'] = __('admin.a11y.dismiss');
@endphp
@pushOnce('styles', 'admin-media-css')
    <link rel="stylesheet" href="{{ ap_asset('css/admin/media.css') }}">
@endPushOnce
@pushOnce('scripts', 'admin-uploader-js')
    <script src="{{ ap_asset('js/admin/uploader.js') }}" defer></script>
@endPushOnce
<form class="adm-dropzone" id="{{ $uploaderId }}" method="POST" action="{{ $action }}" enctype="multipart/form-data"
      data-uploader
      data-uploader-mode="{{ $mode }}"
      data-max-edge="{{ (int) ($mediaConfig['max_edge'] ?? 1920) }}"
      data-quality="{{ (float) ($mediaConfig['quality'] ?? 0.82) }}"
      data-widths="{{ json_encode(array_values(array_map('intval', (array) ($mediaConfig['widths'] ?? [])))) }}"
      data-max-kb="{{ $maxKb }}"
      data-types="{{ json_encode($types) }}"
      data-multiple="{{ $multiple ? '1' : '0' }}"
      data-max-request="{{ $maxBytes }}"
      data-max-file="{{ \App\Cms\Media\MediaManager::MAX_STORED_BYTES }}"
      data-uploader-i18n="{{ json_encode($uploaderStrings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    @csrf
    @foreach ($defaults as $fieldName => $fieldValue)
        @if (is_scalar($fieldValue) || $fieldValue === null)
            <input type="hidden" name="{{ $fieldName }}" value="{{ is_bool($fieldValue) ? (int) $fieldValue : $fieldValue }}">
        @endif
    @endforeach
    <input type="hidden" name="redirect" value="{{ $redirect }}">

    @if ($destination)
        <fieldset class="adm-destination" data-uploader-destination>
            <legend class="adm-destination__legend">{{ __('admin_media.destination.legend') }}</legend>
            <div class="adm-destination__options">
                @foreach (['gallery', 'service', 'library'] as $choice)
                    <label class="adm-destination__option adm-destination__option--{{ $choice }}">
                        <input class="adm-destination__input" type="radio" name="destination" value="{{ $choice }}" @checked($destinationChoice === $choice)>
                        <span class="adm-destination__box">
                            <span class="adm-destination__title">{{ __('admin_media.destination.'.$choice) }}</span>
                            <span class="adm-destination__hint">{{ __('admin_media.destination.'.$choice.'_hint') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="field adm-destination__service">
                <label class="field__label" for="{{ $uploaderId }}-service">{{ __('admin_media.destination.service_label') }}</label>
                <select class="field__input" id="{{ $uploaderId }}-service" name="service_slug" data-uploader-service>
                    <option value="">{{ __('admin_media.destination.service_placeholder') }}</option>
                    @foreach ($services as $serviceSlug => $serviceTitle)
                        <option value="{{ $serviceSlug }}" @selected(old('service_slug') === $serviceSlug)>{{ $serviceTitle }}</option>
                    @endforeach
                </select>
            </div>
        </fieldset>
    @endif

    <div class="adm-dropzone__zone" data-uploader-drop>
        <span class="adm-dropzone__art" aria-hidden="true">
            <span class="adm-dropzone__tri"></span>
            <x-admin.icon name="upload" />
        </span>
        <div class="adm-dropzone__text">
            <p class="adm-dropzone__title">{{ $label }}</p>
            <p class="adm-dropzone__hint">{{ $multiple ? __('admin.uploader.drop') : __('admin.uploader.drop_one') }} <span class="adm-dropzone__or">{{ __('admin.uploader.or') }}</span></p>
        </div>
        {{-- The input comes first: its keyboard focus is drawn on the "browse" label that follows it. --}}
        <input class="adm-dropzone__input" id="{{ $uploaderId }}-input" type="file" name="photo" accept="image/*"
               required data-uploader-input aria-describedby="{{ $uploaderId }}-formats">
        <label class="btn btn--sm adm-dropzone__browse" for="{{ $uploaderId }}-input">
            <x-admin.icon name="image" />
            <span class="btn__label">{{ $multiple ? __('admin.uploader.browse') : __('admin.uploader.browse_one') }}</span>
        </label>
        <p class="adm-dropzone__formats" id="{{ $uploaderId }}-formats">
            {{ __('admin.uploader.hint', ['formats' => $formats, 'max' => $maxMb]) }}
            @if (! empty($hint)) <span class="adm-dropzone__extra">{{ $hint }}</span> @endif
        </p>
        <button class="btn btn--sm btn--secondary adm-dropzone__submit" type="submit" data-uploader-submit>
            <x-admin.icon name="upload" />
            <span class="btn__label">{{ __('admin.uploader.submit') }}</span>
        </button>
    </div>

    <div class="adm-upload-notice-slot" data-uploader-notice aria-live="polite"></div>
    <ol class="adm-uploads" role="list" aria-live="polite" aria-label="{{ __('admin.uploader.list') }}" data-uploader-list hidden></ol>
</form>
