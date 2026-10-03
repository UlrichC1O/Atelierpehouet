{{--
    One exhibition — docs/ARTISTS.md §5.1 (5) / §5.3.
    variant "feature": a wide card of the "Actualité" block (current, upcoming) — visual 16/10 when present (else a
    designed panel with the year), status pill (a current show pulses), kind, title, venue · city, dates,
    description, external link.
    variant "cv" (default): one <li> of the exhibition history — kind, title, venue, city, dates; past shows with a
    visual get a small thumbnail.
    @include('artists.partials.exhibition', ['exhibition' => $exhibition, 'variant' => 'feature'])
--}}
@php
    $expoFeature = ($variant ?? 'cv') === 'feature';
    $expoMedia = $exhibition['media'] ?? null;
    $expoStatus = $exhibition['status'] ?? 'past';
    $expoPlace = array_values(array_filter(
        [$exhibition['venue'] ?? null, $exhibition['city'] ?? null],
        fn ($part) => $part !== null && trim((string) $part) !== '',
    ));
    $expoAlt = $expoMedia ? ($expoMedia->alt(app()->getLocale()) ?: $exhibition['title']) : '';
    $expoThumb = $expoMedia !== null && $expoStatus === 'past';
@endphp
@if ($expoFeature)
    <article class="exhibition-feature exhibition-feature--{{ $expoStatus }}{{ $expoMedia ? ' exhibition-feature--media' : '' }}" data-reveal="fade-up">
        @if ($expoMedia)
            <div class="exhibition-feature__visual">
                @include('artists.partials.image', ['media' => $expoMedia, 'alt' => $expoAlt, 'sizes' => '(min-width: 64em) 36rem, 92vw', 'width' => 960, 'lazy' => true, 'class' => 'exhibition-feature__img', 'cover' => true, 'priority' => false])
            </div>
        @else
            <div class="exhibition-feature__art" aria-hidden="true">
                <span class="exhibition-feature__year">{{ $exhibition['year'] }}</span>
                <span class="exhibition-feature__tri"></span>
            </div>
        @endif
        <div class="exhibition-feature__body">
            <p class="exhibition-feature__tags">
                <span class="exhibition-status exhibition-status--{{ $expoStatus }}{{ $expoStatus === 'current' ? ' ap-anim-scope' : '' }}"><span class="exhibition-status__dot" aria-hidden="true"></span>{{ $exhibition['status_label'] }}</span>
                <span class="exhibition-feature__kind">{{ $exhibition['kind_label'] }}</span>
            </p>
            <h4 class="exhibition-feature__title"><cite>{{ $exhibition['title'] }}</cite></h4>
            <ul class="exhibition-feature__facts" role="list">
                @if ($expoPlace)
                    <li><x-icon name="map-pin" /><span>{{ implode(' · ', $expoPlace) }}</span></li>
                @endif
                <li><x-icon name="calendar" /><span>{{ $exhibition['dates'] }}</span></li>
            </ul>
            @if (! empty($exhibition['description']))
                <p class="exhibition-feature__text">{{ $exhibition['description'] }}</p>
            @endif
            @if (! empty($exhibition['url']))
                <a class="exhibition-feature__link" href="{{ $exhibition['url'] }}" target="_blank" rel="noopener">{{ __('artists.show.exhibitions.more') }}<x-icon name="arrow-up-right" /><span class="visually-hidden"> ({{ __('artists.a11y.new_tab') }})</span></a>
            @endif
        </div>
    </article>
@else
    <li class="exhibition exhibition--{{ $expoStatus }}{{ $expoThumb ? ' exhibition--thumb' : '' }}">
        <div class="exhibition__text">
            <p class="exhibition__kind">
                <span>{{ $exhibition['kind_label'] }}</span>
                @if ($expoStatus !== 'past')
                    <span class="exhibition-status exhibition-status--{{ $expoStatus }}{{ $expoStatus === 'current' ? ' ap-anim-scope' : '' }}"><span class="exhibition-status__dot" aria-hidden="true"></span>{{ $exhibition['status_label'] }}</span>
                @endif
            </p>
            <p class="exhibition__title">
                @if (! empty($exhibition['url']))
                    <a class="exhibition__link" href="{{ $exhibition['url'] }}" target="_blank" rel="noopener"><cite>{{ $exhibition['title'] }}</cite><x-icon name="arrow-up-right" /><span class="visually-hidden"> ({{ __('artists.a11y.new_tab') }})</span></a>
                @else
                    <cite>{{ $exhibition['title'] }}</cite>
                @endif
            </p>
            @if ($expoPlace || ! empty($exhibition['starts_on']))
                <p class="exhibition__place">
                    @if ($expoPlace)<span>{{ implode(', ', $expoPlace) }}</span>@endif
                    @if (! empty($exhibition['starts_on']))<span class="exhibition__dates">{{ $exhibition['dates'] }}</span>@endif
                </p>
            @endif
        </div>
        @if ($expoThumb)
            <span class="exhibition__thumb">
                @include('artists.partials.image', ['media' => $expoMedia, 'alt' => '', 'sizes' => '7rem', 'width' => 480, 'lazy' => true, 'class' => 'exhibition__img', 'cover' => true, 'priority' => false])
            </span>
        @endif
    </li>
@endif
