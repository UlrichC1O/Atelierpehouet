{{--
    One photo of the library grid (docs/CMS.md §7.6).
      @include('admin.media.partials.card', ['card' => $card, 'mode' => 'library'])
    $card: built by Admin\MediaController::cards() — media, item (MediaItem), name, gallery (bool),
    service (?title), spots (list of labels), covers (int), artists (bool), nowhere (bool).
    $mode: library (link "Modifier") | picker ("Choisir": [data-media-choose] + data-media JSON, read by
    media-picker.js) | slot (no-JS flow: "Utiliser ici" form posting to admin.slots.update, needs $slot
    and $redirect). $selected: id of the photo currently chosen (marked .is-selected).
--}}
@php
    $item = $card['item'];
    $mode = in_array($mode ?? 'library', ['library', 'picker', 'slot'], true) ? ($mode ?? 'library') : 'library';
    $isSelected = isset($selected) && (int) $selected === $item->id;
    $editUrl = route('admin.media.edit', $item->id);
    $kilobytes = (int) ceil(max(0, $item->size) / 1024);
    $weight = $kilobytes >= 1024
        ? __('admin_media.card.weight_mb', ['size' => \App\Http\Controllers\Admin\MediaController::megabytes($item->size)])
        : __('admin_media.card.weight', ['size' => $kilobytes]);
    $json = $mode === 'picker' ? json_encode(\App\Http\Controllers\Admin\MediaController::json($card['media']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
@endphp
<li @class(['adm-media-card', 'adm-media-card--nowhere' => $card['nowhere'], 'is-selected' => $isSelected]) data-media-card data-media-id="{{ $item->id }}">
    @if ($mode === 'picker')
        <button class="adm-media-card__media" type="button" data-media-choose data-media="{{ $json }}" tabindex="-1" aria-hidden="true">
    @else
        <a class="adm-media-card__media" href="{{ $editUrl }}" tabindex="-1" aria-hidden="true">
    @endif
        <img class="adm-media-card__img" src="{{ $item->url(480) }}" alt="" width="{{ $item->width }}" height="{{ $item->height }}"
             style="object-position: {{ $item->objectPosition() }}" loading="lazy" decoding="async">
        @if ($item->mime === 'image/gif')
            <span class="adm-media-card__type">GIF</span>
        @endif
    @if ($mode === 'picker')
        </button>
    @else
        </a>
    @endif

    <div class="adm-media-card__body">
        <p class="adm-media-card__name" title="{{ $card['name'] }}">{{ $card['name'] }}</p>
        <p class="adm-media-card__meta">{{ __('admin.common.size', ['width' => $item->width, 'height' => $item->height]) }} · {{ $weight }}</p>
        <div class="adm-media-card__badges">
            @if ($card['gallery'])
                <x-admin.badge variant="success">{{ __('admin_media.card.badges.gallery') }}</x-admin.badge>
            @endif
            @if ($card['service'])
                <x-admin.badge variant="info" title="{{ __('admin_media.card.badges.service') }}">{{ $card['service'] }}</x-admin.badge>
            @endif
            @if ($card['spots'])
                <x-admin.badge variant="custom" title="{{ implode(' · ', $card['spots']) }}">{{ trans_choice('admin_media.card.badges.spots', count($card['spots']), ['count' => count($card['spots'])]) }}</x-admin.badge>
            @endif
            @if ($card['covers'] > 0)
                <x-admin.badge variant="custom">{{ trans_choice('admin_media.card.badges.covers', $card['covers'], ['count' => $card['covers']]) }}</x-admin.badge>
            @endif
            @if ($card['artists'])
                <x-admin.badge variant="custom">{{ __('admin_media.card.badges.artists') }}</x-admin.badge>
            @endif
            @if ($card['nowhere'])
                <x-admin.badge variant="hidden" class="adm-badge--nowhere">{{ __('admin_media.card.badges.nowhere') }}</x-admin.badge>
            @endif
        </div>
    </div>

    <div class="adm-media-card__actions">
        @if ($mode === 'picker')
            <button class="btn btn--sm" type="button" data-media-choose data-media="{{ $json }}" aria-label="{{ __('admin_media.card.choose_label', ['name' => $card['name']]) }}">
                <x-icon name="check" />
                <span class="btn__label">{{ __('admin_media.card.choose') }}</span>
            </button>
        @elseif ($mode === 'slot')
            <form class="adm-media-card__use" method="POST" action="{{ route('admin.slots.update') }}">
                @csrf
                <input type="hidden" name="slot" value="{{ $slot }}">
                <input type="hidden" name="media_id" value="{{ $item->id }}">
                <input type="hidden" name="redirect" value="{{ $redirect }}">
                <button class="btn btn--sm" type="submit" aria-label="{{ __('admin_media.card.use_here_label', ['name' => $card['name']]) }}">
                    <x-icon name="check" />
                    <span class="btn__label">{{ __('admin_media.card.use_here') }}</span>
                </button>
            </form>
            <a class="btn btn--sm btn--ghost" href="{{ $editUrl }}" aria-label="{{ __('admin_media.card.edit_label', ['name' => $card['name']]) }}">{{ __('admin_media.card.edit') }}</a>
        @else
            <a class="btn btn--sm btn--ghost" href="{{ $editUrl }}" aria-label="{{ __('admin_media.card.edit_label', ['name' => $card['name']]) }}">
                <x-admin.icon name="edit" />
                <span class="btn__label">{{ __('admin_media.card.edit') }}</span>
            </a>
        @endif
    </div>
</li>
