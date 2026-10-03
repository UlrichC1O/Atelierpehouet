{{--
    Accent colour of an artist page: one radio swatch per logo accent (docs/ARTISTS.md §6.2).
      @include('admin.artists.partials.accent-field', ['accents' => $accents, 'value' => $artist->accent])
    Vars: $accents (list of config('atelier.accents') keys), $value (current accent). Posts "accent".
--}}
@php
    $accentChoices = array_values(array_filter((array) ($accents ?? config('atelier.accents', [])), 'is_string'));
    $accentOld = old('accent');
    $accentCurrent = is_string($accentOld) && $accentOld !== '' ? $accentOld : (string) ($value ?? \App\Models\Artist::DEFAULT_ACCENT);
    $accentError = isset($errors) ? $errors->first('accent') : null;
    $accentError = $accentError !== '' ? $accentError : null;
@endphp
<fieldset @class(['adm-artist-accents', 'field--invalid' => $accentError]) id="field-accent"
          aria-describedby="field-accent-hint{{ $accentError ? ' field-accent-error' : '' }}">
    <legend class="field__label">{{ __('admin_artists.fields.accent.label') }}</legend>
    <div class="adm-artist-accents__list">
        @foreach ($accentChoices as $accentChoice)
            <label class="adm-artist-swatch accent-{{ $accentChoice }}">
                <input class="adm-artist-swatch__input" type="radio" name="accent" value="{{ $accentChoice }}" @checked($accentCurrent === $accentChoice)>
                <span class="adm-artist-swatch__chip" aria-hidden="true"></span>
                <span class="adm-artist-swatch__name">{{ app('translator')->has('admin_artists.accents.'.$accentChoice) ? __('admin_artists.accents.'.$accentChoice) : ucfirst($accentChoice) }}</span>
            </label>
        @endforeach
    </div>
    <p class="field__hint" id="field-accent-hint">{{ __('admin_artists.fields.accent.hint') }}</p>
    @if ($accentError)
        <p class="field__error" id="field-accent-error">{{ $accentError }}</p>
    @endif
</fieldset>
