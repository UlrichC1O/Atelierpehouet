{{--
    One form field with the site's .field markup (label, control, hint, counter, error).
      <x-admin.field name="title" :label="__('…')" :value="$page->title_fr" :maxlength="120" required />
      <x-admin.field name="t[fr][hero.title]" label="hero.title" type="textarea" lang="fr" :value="$value" />
      <x-admin.field name="category" type="select" :options="['peinture' => 'Peinture', 'Groupe' => ['a' => 'A']]" />
    type: text|email|url|tel|number|password|textarea|select. Other attributes (autocomplete, min,
    step, inputmode, data-*…) go on the control; `class` goes on the wrapper; `id` replaces the generated id.

    Error key (read from $errors) = the name with "[" ⇒ "." and "]" removed, outer dots trimmed:
      t[fr][hero.title] ⇒ t.fr.hero.title · features[0][title] ⇒ features.0.title · photos[] ⇒ photos
    (Laravel's own convention, so $request->validate() errors match; hand-built error bags must use it.)
    Control id = "field-" + that key with every run of other characters than [A-Za-z0-9_-] turned into
    "-" (t.fr.hero.title ⇒ field-t-fr-hero-title); hint = {id}-hint, error = {id}-error. The error
    summary of the layout links to the same ids.
    Value shown = old(key) when the request flashed input, else :value (passwords are never refilled).
    maxlength ⇒ maxlength + data-maxlength + a live .adm-counter; textareas autosize unless :autosize="false".
--}}
@props([
    'name',
    'label',
    'value' => null,
    'type' => 'text',
    'hint' => null,
    'rows' => 3,
    'options' => [],
    'maxlength' => null,
    'required' => false,
    'placeholder' => null,
    'lang' => null,
    'autosize' => true,
])
@php
    $errorKey = trim(str_replace(['[', ']'], ['.', ''], (string) $name), '.');
    $id = (string) ($attributes->get('id') ?: 'field-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $errorKey), '-'));
    $bag = isset($errors) ? $errors : null;
    $error = $bag ? $bag->first($errorKey) : null;
    $error = $error !== '' ? $error : null;
    $isPassword = $type === 'password';
    $current = $isPassword ? null : old($errorKey, $value);
    $current = is_scalar($current) ? (string) $current : null;
    $maxlength = $maxlength !== null && (int) $maxlength > 0 ? (int) $maxlength : null;
    $lang = in_array($lang, array_keys((array) config('atelier.locales', [])), true) ? $lang : null;
    $describedBy = trim(($hint !== null || $maxlength ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
    $control = $attributes->except(['class', 'id'])->merge(array_filter([
        'id' => $id,
        'name' => $name,
        'required' => (bool) $required,
        'placeholder' => $placeholder,
        'lang' => $lang,
        'maxlength' => $maxlength,
        'data-maxlength' => $maxlength,
        'aria-describedby' => $describedBy !== '' ? $describedBy : null,
        'aria-invalid' => $error ? 'true' : null,
    ], fn ($attribute) => $attribute !== null && $attribute !== false));
@endphp
<div {{ $attributes->only('class')->class(['field', 'adm-field', 'adm-field--'.$type, 'field--invalid' => $error]) }}>
    <label class="field__label" for="{{ $id }}">
        <span>{{ $label }}</span>
        @if ($required)
            <span class="adm-field__req" aria-hidden="true">*</span>
        @endif
        @if ($lang)
            <span class="adm-lang adm-lang--{{ $lang }}"><span aria-hidden="true">{{ strtoupper($lang) }}</span><span class="visually-hidden">({{ __('admin.common.'.($lang === 'fr' ? 'french' : 'english')) }})</span></span>
        @endif
    </label>

    @if ($type === 'textarea')
        <textarea {{ $control->class(['field__input'])->merge(['rows' => (int) $rows]) }} @if ($autosize) data-autosize @endif>{{ $current }}</textarea>
    @elseif ($type === 'select')
        <select {{ $control->except(['maxlength', 'data-maxlength', 'placeholder'])->class(['field__input']) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $optionValue => $optionLabel)
                @if (is_array($optionLabel))
                    <optgroup label="{{ $optionValue }}">
                        @foreach ($optionLabel as $groupValue => $groupLabel)
                            <option value="{{ $groupValue }}" @selected($current !== null && (string) $groupValue === $current)>{{ $groupLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    <option value="{{ $optionValue }}" @selected($current !== null && (string) $optionValue === $current)>{{ $optionLabel }}</option>
                @endif
            @endforeach
        </select>
    @elseif ($isPassword)
        <div class="adm-field__control">
            <input {{ $control->class(['field__input'])->merge(['type' => 'password']) }}>
            <button class="adm-field__reveal" type="button" data-adm-reveal="{{ $id }}" aria-controls="{{ $id }}" aria-pressed="false" hidden>
                <x-admin.icon name="eye" />
                <span class="visually-hidden" data-adm-reveal-label>{{ __('admin.a11y.show_password') }}</span>
            </button>
        </div>
    @else
        <input {{ $control->class(['field__input'])->merge(['type' => in_array($type, ['text', 'email', 'url', 'tel', 'number'], true) ? $type : 'text', 'value' => $current]) }}>
    @endif

    @if ($hint !== null || $maxlength)
        <div class="adm-field__foot">
            <p class="field__hint" id="{{ $id }}-hint">
                @if ($hint !== null){{ $hint }}@endif
                @if ($maxlength)<span class="visually-hidden">{{ __('admin.common.max_chars', ['max' => $maxlength]) }}</span>@endif
            </p>
            @if ($maxlength)
                <span class="adm-counter" data-counter-for="{{ $id }}" aria-hidden="true">{{ __('admin.common.counter', ['count' => mb_strlen((string) $current), 'max' => $maxlength]) }}</span>
            @endif
        </div>
    @endif

    @if ($error)
        <p class="field__error" id="{{ $id }}-error">{{ $error }}</p>
    @endif
</div>
