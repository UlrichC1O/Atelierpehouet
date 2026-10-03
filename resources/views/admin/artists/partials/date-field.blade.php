{{--
    A date field (<input type="date">, value Y-m-d) with the markup of <x-admin.field>, which has no date type.
      @include('admin.artists.partials.date-field', ['name' => 'starts_on', 'label' => __('…'), 'value' => '2025-03-12',
               'hint' => __('…'), 'hook' => 'data-exhibition-start'])
    Vars: $name, $label, $value (?string Y-m-d), $hint = null, $hook = null (one data-* attribute for artists.js),
    $min / $max = null (Y-m-d). Same error key and ids as <x-admin.field>: field-{name}, -hint, -error.
--}}
@php
    $dateKey = trim(str_replace(['[', ']'], ['.', ''], (string) $name), '.');
    $dateId = 'field-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $dateKey), '-');
    $dateError = isset($errors) ? $errors->first($dateKey) : null;
    $dateError = $dateError !== '' ? $dateError : null;
    $dateOld = old($dateKey, $value ?? null);
    $dateValue = is_scalar($dateOld) ? (string) $dateOld : '';
    $dateHint = $hint ?? null;
    $dateHook = isset($hook) && preg_match('/^data-[a-z-]+$/', (string) $hook) === 1 ? (string) $hook : null;
    $dateDescribedBy = trim(($dateHint !== null ? $dateId.'-hint ' : '').($dateError ? $dateId.'-error' : ''));
@endphp
<div @class(['field', 'adm-field', 'adm-field--date', 'field--invalid' => $dateError])>
    <label class="field__label" for="{{ $dateId }}"><span>{{ $label }}</span></label>
    <input class="field__input" type="date" id="{{ $dateId }}" name="{{ $name }}" value="{{ $dateValue }}"
           @if (! empty($min)) min="{{ $min }}" @endif @if (! empty($max)) max="{{ $max }}" @endif
           @if ($dateHook) {{ $dateHook }} @endif
           @if ($dateDescribedBy !== '') aria-describedby="{{ $dateDescribedBy }}" @endif
           @if ($dateError) aria-invalid="true" @endif>
    @if ($dateHint !== null)
        <p class="field__hint" id="{{ $dateId }}-hint">{{ $dateHint }}</p>
    @endif
    @if ($dateError)
        <p class="field__error" id="{{ $dateId }}-error">{{ $dateError }}</p>
    @endif
</div>
