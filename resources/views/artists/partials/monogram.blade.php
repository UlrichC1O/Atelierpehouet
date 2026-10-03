{{--
    Monogram shown when an artist has neither a portrait nor a photographed work (docs/ARTISTS.md §5.1):
    the initials in outlined display type over a slowly shifting Mondrian field in the artist's accent colour.
    Fills its positioned frame. Decorative: the artist's name is always written next to it.
    @include('artists.partials.monogram', ['artist' => $summary])
--}}
<div class="artist-monogram accent-{{ $artist['accent'] }} ap-anim-scope" aria-hidden="true">
    <span class="artist-monogram__field">
        <span class="artist-monogram__block artist-monogram__block--a" style="--i: 0"></span>
        <span class="artist-monogram__block artist-monogram__block--b" style="--i: 1"></span>
        <span class="artist-monogram__block artist-monogram__block--c" style="--i: 2"></span>
        <span class="artist-monogram__block artist-monogram__block--d" style="--i: 3"></span>
        <span class="artist-monogram__block artist-monogram__block--e" style="--i: 4"></span>
    </span>
    <span class="artist-monogram__initials">{{ $artist['initials'] }}</span>
</div>
