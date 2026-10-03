{{--
    Photo uploader (docs/CMS.md §6/§7.6) — markup by admin-ui, behaviour by admin-media (uploader.js).
      @include('admin.media.partials.uploader', ['defaults' => ['in_gallery' => 1], 'multiple' => true])
    Vars: $action (default route('admin.media.store')), $defaults (hidden fields, e.g. service_slug,
    in_gallery, slot), $multiple (bool, default true), $redirect (default: this page), $label (title).
    Never place it inside another <form>.

    Without JavaScript: a real multipart form posting ONE file ("photo"; `multiple` is only switched on
    by the script). form[data-uploader] carries the client settings from config('cms.media'):
      data-max-edge, data-quality, data-widths (JSON), data-max-kb, data-types (JSON list of MIME types),
      data-multiple ("1"/"0"), data-max-request (bytes per request)
    Inner hooks: [data-uploader-drop] (drop zone, gets .is-dragover / .is-busy on the form),
    input[data-uploader-input] (the file input), [data-uploader-submit] (no-JS submit, hidden by the
    script), ol[data-uploader-list] (.adm-uploads status list, aria-live; rows documented in admin.css).
--}}
@php
    $action = $action ?? route('admin.media.store');
    $defaults = (array) ($defaults ?? []);
    $multiple = (bool) ($multiple ?? true);
    $redirect = $redirect ?? request()->fullUrl();
    $mediaConfig = (array) config('cms.media', []);
    $maxKb = (int) ($mediaConfig['max_kb'] ?? 8192);
    $maxMb = str_replace('.', app()->getLocale() === 'fr' ? ',' : '.', (string) round($maxKb / 1024, 1));
    $types = array_keys((array) ($mediaConfig['types'] ?? []));
    // "JPEG, PNG, WebP ou GIF", from the accepted types (the hint never lists a refused format).
    $formatNames = array_values(array_unique(array_map(
        fn ($extension) => ['jpg' => 'JPEG', 'jpeg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WebP', 'gif' => 'GIF', 'avif' => 'AVIF'][strtolower((string) $extension)] ?? strtoupper((string) $extension),
        array_values((array) ($mediaConfig['types'] ?? [])),
    )));
    $lastFormat = array_pop($formatNames);
    $formats = $formatNames ? implode(', ', $formatNames).' '.__('admin.common.or').' '.$lastFormat : (string) $lastFormat;
    $label = $label ?? ($multiple ? __('admin.uploader.title') : __('admin.uploader.title_one'));
    $uploaderId = 'uploader-'.substr(md5($action.'|'.json_encode($defaults)), 0, 8);
@endphp
<form class="adm-dropzone" id="{{ $uploaderId }}" method="POST" action="{{ $action }}" enctype="multipart/form-data"
      data-uploader
      data-max-edge="{{ (int) ($mediaConfig['max_edge'] ?? 2048) }}"
      data-quality="{{ (float) ($mediaConfig['quality'] ?? 0.82) }}"
      data-widths="{{ json_encode(array_values(array_map('intval', (array) ($mediaConfig['widths'] ?? [])))) }}"
      data-max-kb="{{ $maxKb }}"
      data-types="{{ json_encode($types) }}"
      data-multiple="{{ $multiple ? '1' : '0' }}"
      data-max-request="4000000">
    @csrf
    @foreach ($defaults as $fieldName => $fieldValue)
        @if (is_scalar($fieldValue) || $fieldValue === null)
            <input type="hidden" name="{{ $fieldName }}" value="{{ is_bool($fieldValue) ? (int) $fieldValue : $fieldValue }}">
        @endif
    @endforeach
    <input type="hidden" name="redirect" value="{{ $redirect }}">

    <div class="adm-dropzone__zone" data-uploader-drop>
        <span class="adm-dropzone__art" aria-hidden="true">
            <span class="adm-dropzone__tri"></span>
            <x-admin.icon name="upload" />
        </span>
        <div class="adm-dropzone__text">
            <p class="adm-dropzone__title">{{ $label }}</p>
            <p class="adm-dropzone__hint">{{ $multiple ? __('admin.uploader.drop') : __('admin.uploader.drop_one') }} <span class="adm-dropzone__or">{{ __('admin.uploader.or') }}</span></p>
        </div>
        <label class="btn btn--sm adm-dropzone__browse" for="{{ $uploaderId }}-input">
            <x-admin.icon name="image" />
            <span class="btn__label">{{ $multiple ? __('admin.uploader.browse') : __('admin.uploader.browse_one') }}</span>
        </label>
        <input class="adm-dropzone__input" id="{{ $uploaderId }}-input" type="file" name="photo" accept="image/*"
               required data-uploader-input aria-describedby="{{ $uploaderId }}-formats">
        <p class="adm-dropzone__formats" id="{{ $uploaderId }}-formats">{{ __('admin.uploader.hint', ['formats' => $formats, 'max' => $maxMb]) }}</p>
        <button class="btn btn--sm btn--secondary adm-dropzone__submit" type="submit" data-uploader-submit>
            <x-admin.icon name="upload" />
            <span class="btn__label">{{ __('admin.uploader.submit') }}</span>
        </button>
    </div>

    <ol class="adm-uploads" role="list" aria-live="polite" aria-label="{{ __('admin.uploader.list') }}" data-uploader-list hidden></ol>
</form>
