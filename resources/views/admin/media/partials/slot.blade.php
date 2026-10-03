{{--
    A fixed photo spot of a page (docs/CMS.md §6/§7.6) — markup by admin-ui, behaviour by admin-media.
      @include('admin.media.partials.slot', ['slot' => 'home.feature', 'label' => $slots->label('home.feature'),
               'media' => cms()->slot('home.feature'), 'ratio' => '4/3', 'redirect' => request()->fullUrl()])
    Vars: $slot (key), $label, $media (?App\Cms\MediaItem), $ratio ("4/3"), $redirect (URL, default: this page).

    Hooks: form[data-slot-form] posts slot + media_id + redirect to admin.slots.update; its
    a[data-media-picker][data-slot] opens the picker (media-picker.js fills that form's media_id and
    submits) and, without JavaScript, leads to admin.media.index?slot=…&redirect=… ("Utiliser ici").
    "Retirer" is a second form posting an empty media_id.
--}}
@php
    $slotKey = (string) $slot;
    $label = $label ?? $slotKey;
    $media = $media ?? null;
    $hasMedia = is_object($media) && method_exists($media, 'url');
    $ratio = isset($ratio) && preg_match('#^\d+(\.\d+)?/\d+(\.\d+)?$#', (string) $ratio) ? (string) $ratio : '4/3';
    $redirect = $redirect ?? request()->fullUrl();
    $slotId = 'slot-'.trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($slotKey)), '-');
    $libraryUrl = route('admin.media.index', ['slot' => $slotKey, 'redirect' => $redirect]);

    // "Voir sur la page" (docs/CMS.md §13 F31): the public page showing this spot, at #spot-{slot-with-dashes}.
    $viewUrl = null;
    if ($hasMedia) {
        $anchor = '#spot-'.str_replace('.', '-', $slotKey);
        if (preg_match('/^service\.([a-z0-9-]+)\.cover$/', $slotKey, $serviceMatch)) {
            $viewUrl = \Illuminate\Support\Facades\Route::has('services.show') ? route('services.show', $serviceMatch[1]).$anchor : null;
        } elseif ($slotKey !== 'site.share') {
            $slotPage = ((array) config('cms.slots', []))[$slotKey]['page'] ?? null;
            $pageRoute = is_string($slotPage) ? (((array) config('cms.editable_groups', []))[$slotPage] ?? null) : null;
            $viewUrl = is_string($pageRoute) && \Illuminate\Support\Facades\Route::has($pageRoute) ? route($pageRoute).$anchor : null;
        }
    }
@endphp
<div class="adm-slot" id="{{ $slotId }}" data-slot-card data-slot-key="{{ $slotKey }}">
    <div class="adm-slot__frame" style="aspect-ratio: {{ str_replace('/', ' / ', $ratio) }}">
        @if ($hasMedia)
            <img class="adm-slot__img" src="{{ $media->url(960) }}"
                 @if (method_exists($media, 'srcset')) srcset="{{ $media->srcset() }}" sizes="(min-width: 64em) 24rem, 92vw" @endif
                 alt="{{ __('admin.slot.current', ['alt' => method_exists($media, 'alt') ? $media->alt() : '']) }}"
                 style="object-position: {{ method_exists($media, 'objectPosition') ? $media->objectPosition() : '50% 50%' }}"
                 loading="lazy" decoding="async">
        @else
            <div class="adm-slot__empty">
                <span class="adm-slot__tri" aria-hidden="true"></span>
                <span class="adm-slot__empty-text">{{ __('admin.slot.empty') }}</span>
            </div>
        @endif
        <span class="adm-slot__ratio">{{ __('admin.slot.ratio', ['ratio' => str_replace('/', ':', $ratio)]) }}</span>
    </div>

    <div class="adm-slot__body">
        <p class="adm-slot__label">{{ $label }}</p>
        @if ($hasMedia)
            <p class="adm-slot__meta">
                {{ $media->originalName ?? '' }}
                @if (! empty($media->width) && ! empty($media->height))
                    <span>{{ __('admin.common.size', ['width' => $media->width, 'height' => $media->height]) }}</span>
                @endif
            </p>
        @else
            <p class="adm-slot__meta">{{ __('admin.slot.empty_hint') }}</p>
        @endif

        <div class="adm-slot__actions">
            <form class="adm-slot__form" method="POST" action="{{ route('admin.slots.update') }}" data-slot-form>
                @csrf
                <input type="hidden" name="slot" value="{{ $slotKey }}">
                <input type="hidden" name="media_id" value="{{ $hasMedia ? $media->id : '' }}">
                <input type="hidden" name="redirect" value="{{ $redirect }}">
                <a class="btn btn--sm btn--secondary" href="{{ $libraryUrl }}" data-media-picker data-slot="{{ $slotKey }}">
                    <x-admin.icon name="image" />
                    <span class="btn__label">{{ $hasMedia ? __('admin.slot.change') : __('admin.slot.choose') }}</span>
                </a>
            </form>
            @if ($hasMedia)
                @if ($viewUrl)
                    <a class="btn btn--sm btn--ghost" href="{{ $viewUrl }}" target="_blank" rel="noopener">
                        <x-icon name="external" />
                        <span class="btn__label">{{ __('admin.slot.view') }}</span>
                        <span class="visually-hidden">({{ __('admin.a11y.new_tab') }})</span>
                    </a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('admin.media.edit'))
                    <a class="btn btn--sm btn--ghost" href="{{ route('admin.media.edit', $media->id) }}">
                        <span class="btn__label">{{ __('admin.slot.edit') }}</span>
                    </a>
                @endif
                <form class="adm-slot__form" method="POST" action="{{ route('admin.slots.update') }}">
                    @csrf
                    <input type="hidden" name="slot" value="{{ $slotKey }}">
                    <input type="hidden" name="media_id" value="">
                    <input type="hidden" name="redirect" value="{{ $redirect }}">
                    <button class="btn btn--sm btn--link adm-slot__remove" type="submit">{{ __('admin.slot.remove') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
