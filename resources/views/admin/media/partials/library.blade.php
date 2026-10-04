{{--
    Filters, search, photo grid and pagination of the library (docs/CMS.md §7.6) — shared by the full
    page (admin.media.index) and the picker's partial (admin.media.picker, ?picker=1).
    Vars (Admin\MediaController::libraryData): $photos (paginator, 48 per page), $cards, $services,
    $filter (all|gallery|service|unused), $search, $service, $counts, $picker, $slot, $redirect;
    $mode (library|picker|slot), $selected (?int).
    In the picker, media-picker.js loads the filter form and the pagination links inside the dialog
    (form[data-picker-form], links of .adm-pagination / [data-picker-link]).
--}}
@php
    $mode = $mode ?? 'library';
    $selected = $selected ?? null;
    // Query kept by every filter link: the picker / slot modes and the search.
    $keep = array_filter([
        'picker' => $picker ? 1 : null,
        'slot' => $slot,
        'redirect' => $slot ? $redirect : null,
        'selected' => $selected,
    ], fn ($value) => $value !== null && $value !== '');
    $filterUrl = fn (array $query) => route('admin.media.index', array_filter($query + $keep, fn ($value) => $value !== null && $value !== ''));
    $chips = [
        'all' => ['url' => $filterUrl(['q' => $search]), 'count' => $counts['all']],
        'gallery' => ['url' => $filterUrl(['filter' => 'gallery', 'q' => $search]), 'count' => $counts['gallery']],
        'unused' => ['url' => $filterUrl(['filter' => 'unused', 'q' => $search]), 'count' => $counts['unused']],
    ];
    $filtered = $filter !== 'all' || $search !== '';
    $searchId = $mode === 'picker' ? 'picker-search' : 'library-search';
    $serviceId = $mode === 'picker' ? 'picker-service' : 'library-service';
@endphp
<div class="adm-library" data-media-library>
    <form class="adm-media-filters" method="GET" action="{{ route('admin.media.index') }}" role="search"
          aria-label="{{ __('admin_media.library.filters_label') }}" @if ($mode === 'picker') data-picker-form @endif>
        @foreach ($keep as $keepName => $keepValue)
            <input type="hidden" name="{{ $keepName }}" value="{{ $keepValue }}">
        @endforeach
        @if (in_array($filter, ['gallery', 'unused'], true))
            <input type="hidden" name="filter" value="{{ $filter }}">
        @endif

        <ul class="adm-media-filters__chips" role="list">
            @foreach ($chips as $chipKey => $chip)
                <li>
                    <a @class(['chip', 'adm-media-filters__chip', 'is-active' => $filter === $chipKey]) href="{{ $chip['url'] }}"
                       @if ($mode === 'picker') data-picker-link @endif @if ($filter === $chipKey) aria-current="page" @endif>
                        <span>{{ __('admin_media.library.filters.'.$chipKey) }}</span>
                        <span class="chip__count">{{ $chip['count'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="adm-media-filters__fields">
            @if ($services)
                <div class="field adm-media-filters__service">
                    <label class="field__label" for="{{ $serviceId }}">{{ __('admin_media.library.filters.service') }}</label>
                    <select class="field__input" id="{{ $serviceId }}" name="service" data-media-autosubmit>
                        <option value="">{{ __('admin_media.library.service_any') }}</option>
                        @foreach ($services as $serviceSlug => $serviceTitle)
                            <option value="{{ $serviceSlug }}" @selected($service === $serviceSlug)>{{ $serviceTitle }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="field adm-media-filters__search">
                <label class="field__label" for="{{ $searchId }}">{{ __('admin_media.library.search_label') }}</label>
                <span class="adm-search">
                    <x-admin.icon name="search" />
                    <input class="field__input" id="{{ $searchId }}" type="search" name="q" value="{{ $search }}" maxlength="100"
                           placeholder="{{ __('admin_media.library.search_placeholder') }}" @if ($mode === 'picker') data-adm-dialog-focus @endif>
                </span>
            </div>
            <button class="btn btn--sm btn--secondary adm-media-filters__submit" type="submit">
                <x-admin.icon name="search" />
                <span class="btn__label">{{ __('admin_media.library.search_submit') }}</span>
            </button>
        </div>
    </form>

    @if ($filter === 'unused')
        <p class="adm-library__note">{{ __('admin_media.library.unused_hint') }}</p>
    @endif

    @if ($cards)
        <p class="adm-library__count" role="status">
            @if ($filtered)
                {{ trans_choice('admin_media.library.results', $photos->total(), ['count' => $photos->total()]) }}
                <a class="adm-library__reset" href="{{ $filterUrl([]) }}" @if ($mode === 'picker') data-picker-link @endif>{{ __('admin_media.library.reset') }}</a>
            @else
                {{ trans_choice('admin_media.library.count', $photos->total(), ['count' => $photos->total()]) }}
            @endif
        </p>

        <ul class="adm-media-grid adm-library__grid" role="list" aria-label="{{ __('admin_media.library.grid_label') }}">
            @foreach ($cards as $card)
                @include('admin.media.partials.card', ['card' => $card, 'mode' => $mode, 'slot' => $slot, 'redirect' => $redirect, 'selected' => $selected])
            @endforeach
        </ul>

        <div class="adm-library__pages" @if ($mode === 'picker') data-picker-pages @endif>
            {{ $photos->links('admin.partials.pagination') }}
        </div>
    @elseif ($filtered)
        <x-admin.empty :title="__('admin_media.library.empty_filtered_title')" :text="__('admin_media.library.empty_filtered_text')" icon="search">
            <a class="btn btn--sm btn--ghost" href="{{ $filterUrl([]) }}" @if ($mode === 'picker') data-picker-link @endif>{{ __('admin_media.library.reset') }}</a>
        </x-admin.empty>
    @else
        <x-admin.empty :title="__('admin_media.library.empty_title')" :text="__('admin_media.library.empty_text')" icon="image" />
    @endif
</div>
