{{--
    On/off switch posting 0 or 1 (a hidden "0" precedes the checkbox "1", so unticking is saved too).
      <x-admin.toggle name="in_gallery" :label="__('…')" :checked="$media->inGallery" :hint="__('…')" />
    Same error-key and id rules as <x-admin.field> (name in_gallery ⇒ error key in_gallery, id field-in_gallery).
    Extra attributes go on the checkbox (e.g. data-*), `class` on the wrapper.
--}}
@props([
    'name',
    'label',
    'checked' => false,
    'hint' => null,
])
@php
    $errorKey = trim(str_replace(['[', ']'], ['.', ''], (string) $name), '.');
    $id = (string) ($attributes->get('id') ?: 'field-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $errorKey), '-'));
    $bag = isset($errors) ? $errors : null;
    $error = $bag ? $bag->first($errorKey) : null;
    $error = $error !== '' ? $error : null;
    $old = old($errorKey);
    $isChecked = is_scalar($old) ? filter_var($old, FILTER_VALIDATE_BOOLEAN) : (bool) $checked;
    $describedBy = trim(($hint !== null ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div {{ $attributes->only('class')->class(['adm-toggle', 'field--invalid' => $error]) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input {{ $attributes->except(['class', 'id'])->class(['adm-toggle__input'])->merge(array_filter([
        'type' => 'checkbox',
        'role' => 'switch',
        'id' => $id,
        'name' => $name,
        'value' => '1',
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        'aria-invalid' => $error ? 'true' : null,
    ])) }} @checked($isChecked)>
    <label class="adm-toggle__label" for="{{ $id }}">
        <span class="adm-toggle__track" aria-hidden="true"><span class="adm-toggle__thumb"></span></span>
        <span class="adm-toggle__text">{{ $label }}</span>
    </label>
    @if ($hint !== null)
        <p class="field__hint adm-toggle__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if ($error)
        <p class="field__error adm-toggle__error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</div>
