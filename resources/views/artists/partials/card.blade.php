{{--
    Artist card (index grid, "Autres artistes") — docs/ARTISTS.md §5.2 / §5.3.
    The whole card is one link (the link of the name stretches over the card, so its accessible name stays the
    artist's name): portrait (cropped on its focal point) → key work (never cropped, on a wall mat) → monogram, in a
    4/5 seam frame with an accent triangle corner; discipline, name, location, counts and an arrow.
    @include('artists.partials.card', ['artist' => $summary, 'index' => $loop->index])
    Optional: level (heading level, 3) · feature (false; true = wide card with the statement, for a lone artist).
--}}
@php
    $cardPortrait = $artist['portrait'] ?? null;
    $cardWork = $cardPortrait ? null : ($artist['cover'] ?? null);
    $cardFeature = ! empty($feature);
    $cardLevel = max(2, min(4, (int) ($level ?? 3)));
    $cardCounts = array_values(array_filter([
        ($artist['counts']['artworks'] ?? 0) > 0 ? trans_choice('artists.counts.artworks', $artist['counts']['artworks']) : null,
        ($artist['counts']['exhibitions'] ?? 0) > 0 ? trans_choice('artists.counts.exhibitions', $artist['counts']['exhibitions']) : null,
    ]));
    $cardSizes = $cardFeature
        ? '(min-width: 64em) 28rem, (min-width: 40em) 40vw, 92vw'
        : '(min-width: 80em) 24rem, (min-width: 64em) 30vw, (min-width: 40em) 46vw, 92vw';
@endphp
<article class="artist-card accent-{{ $artist['accent'] }}{{ $cardFeature ? ' artist-card--feature' : '' }}" style="--i: {{ (int) ($index ?? 0) }}" data-reveal="fade-up">
    <div class="artist-card__visual">
        @if ($cardPortrait)
            @include('artists.partials.image', ['media' => $cardPortrait, 'alt' => '', 'sizes' => $cardSizes, 'width' => 960, 'lazy' => true, 'class' => 'artist-card__img', 'cover' => true, 'priority' => false])
        @elseif ($cardWork)
            <span class="artist-card__mat">
                <span class="artist-card__work" style="aspect-ratio: {{ $cardWork->ratio() }}; --ratio: {{ round($cardWork->width / max(1, $cardWork->height), 4) }}">
                    @include('artists.partials.image', ['media' => $cardWork, 'alt' => '', 'sizes' => $cardSizes, 'width' => 960, 'lazy' => true, 'class' => 'artist-card__img', 'cover' => false, 'priority' => false])
                </span>
            </span>
        @else
            @include('artists.partials.monogram', ['artist' => $artist])
        @endif
        <span class="artist-card__seam" aria-hidden="true"></span>
        <span class="artist-card__corner" aria-hidden="true"></span>
    </div>
    <div class="artist-card__body">
        @if (! empty($artist['discipline']))
            <p class="artist-card__discipline">{{ $artist['discipline'] }}</p>
        @endif
        <h{{ $cardLevel }} class="artist-card__name"><a class="artist-card__link" href="{{ $artist['url'] }}">{{ $artist['name'] }}</a></h{{ $cardLevel }}>
        @if (! empty($artist['location']))
            <p class="artist-card__location"><x-icon name="map-pin" /><span class="visually-hidden">{{ __('artists.a11y.location') }} </span>{{ $artist['location'] }}</p>
        @endif
        @if ($cardFeature && ! empty($artist['statement']))
            <p class="artist-card__statement">{{ $artist['statement'] }}</p>
        @endif
        <p class="artist-card__foot">
            @if ($cardCounts)
                <span class="artist-card__counts">{{ implode(' · ', $cardCounts) }}</span>
            @endif
            <span class="artist-card__more" aria-hidden="true">{{ __('artists.card.more') }}<x-icon name="arrow-right" /></span>
        </p>
    </div>
</article>
