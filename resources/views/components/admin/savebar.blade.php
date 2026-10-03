{{--
    Sticky save bar, placed as the LAST child of a <form data-adm-dirty> (it submits that form).
      <x-admin.savebar :back="route('admin.pages.index')" />            label defaults to "Enregistrer"
      <x-admin.savebar :label="__('…')">3 textes modifiés</x-admin.savebar>   ← slot = extra status text
    admin.js toggles .is-dirty on it while the form has unsaved changes (and warns before leaving).
--}}
@props([
    'label' => null,
    'back' => null,
])
<div {{ $attributes->class(['adm-savebar']) }} data-adm-savebar>
    <div class="adm-savebar__inner">
        <p class="adm-savebar__status">
            <span class="adm-savebar__dot" aria-hidden="true"></span>
            <span data-adm-dirty-status data-clean="{{ __('admin.common.all_saved') }}" data-dirty="{{ __('admin.common.unsaved') }}">{{ __('admin.common.all_saved') }}</span>
            @if ($slot->isNotEmpty())
                <span class="adm-savebar__extra">{{ $slot }}</span>
            @endif
        </p>
        <div class="adm-savebar__actions">
            @if ($back)
                <a class="btn btn--sm btn--ghost" href="{{ $back }}">{{ __('admin.actions.cancel') }}</a>
            @endif
            <button class="btn adm-savebar__submit" type="submit">
                <x-admin.icon name="save" />
                <span class="btn__label">{{ $label ?? __('admin.actions.save') }}</span>
            </button>
        </div>
    </div>
</div>
