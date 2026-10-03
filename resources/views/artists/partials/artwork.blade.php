{{--
    One work of the "Œuvres choisies" grid — docs/ARTISTS.md §5.1 (4) / §5.3.
    A "wall mat" holds the photo at its natural ratio (never cropped: the frame takes the photo's aspect-ratio and
    --ratio = width / height) and opens the lightbox on the 1600 px file; museum-label caption: title in italics,
    year · technique · dimensions, availability pill, notice in a <details>.
    Without a photo: a designed Mondrian placeholder in the same mat.
    @include('artists.partials.artwork', ['artwork' => $artwork, 'artist' => $artist])
--}}
@php
    $workMedia = $artwork['media'] ?? null;
    $workLandscape = ! empty($artwork['landscape']);
    // Year and dimensions never break inside ("120 × 150 cm"); the technique may wrap.
    $workKeep = fn (?string $part): ?string => $part === null || trim($part) === '' ? null : str_replace(' ', "\u{00A0}", trim($part));
    $workMeta = array_values(array_filter(
        [$workKeep($artwork['year'] ?? null), $artwork['medium'] ?? null, $workKeep($artwork['dimensions'] ?? null)],
        fn ($part) => $part !== null && trim((string) $part) !== '',
    ));
    $workSizes = $workLandscape
        ? '(min-width: 80em) 40rem, (min-width: 64em) 52vw, (min-width: 40em) 44vw, 92vw'
        : '(min-width: 80em) 20rem, (min-width: 64em) 26vw, (min-width: 40em) 44vw, 92vw';
@endphp
<figure class="artwork{{ $workLandscape ? ' artwork--landscape' : '' }}{{ $workMedia ? '' : ' artwork--placeholder' }}"
        data-filter-item data-category="{{ $artwork['availability'] }}" data-reveal="fade-up">
    @if ($workMedia)
        <a class="artwork__mat" href="{{ $workMedia->url(1600) }}" data-lightbox="artworks" data-caption="{{ $artwork['caption'] }}">
            <span class="artwork__frame" style="aspect-ratio: {{ $workMedia->ratio() }}; --ratio: {{ round($workMedia->width / max(1, $workMedia->height), 4) }}">
                @include('artists.partials.image', ['media' => $workMedia, 'alt' => $artwork['alt'], 'sizes' => $workSizes, 'width' => 960, 'lazy' => true, 'class' => 'artwork__img', 'cover' => false, 'priority' => false])
                <span class="artwork__sheen" aria-hidden="true"></span>
            </span>
            <span class="artwork__zoom" aria-hidden="true"><x-icon name="plus" /></span>
            <span class="visually-hidden">{{ __('artists.show.works.zoom') }}</span>
        </a>
    @else
        <div class="artwork__mat artwork__mat--empty">
            <span class="artwork__placeholder" aria-hidden="true">
                <span class="artwork__field artwork__field--a"></span>
                <span class="artwork__field artwork__field--b"></span>
                <span class="artwork__field artwork__field--c"></span>
                <span class="artwork__field artwork__field--d"></span>
                <span class="artwork__field artwork__field--e"></span>
            </span>
            <span class="artwork__placeholder-label">{{ __('artists.show.works.no_image') }}</span>
        </div>
    @endif
    <figcaption class="artwork__caption">
        <h3 class="artwork__title"><cite>{{ $artwork['title'] }}</cite></h3>
        @if ($workMeta)
            <p class="artwork__meta">{{ implode("\u{00A0}· ", $workMeta) }}</p>
        @endif
        @if (! empty($artwork['availability_label']))
            <p class="artwork__availability"><span class="artwork__dot artwork__dot--{{ $artwork['availability'] }}" aria-hidden="true"></span>{{ $artwork['availability_label'] }}</p>
        @endif
        @if (! empty($artwork['description']))
            <details class="artwork__details">
                <summary class="artwork__summary">{{ __('artists.show.works.details') }}<span class="artwork__chevron" aria-hidden="true"></span></summary>
                <p class="artwork__description">{{ $artwork['description'] }}</p>
            </details>
        @endif
    </figcaption>
</figure>
