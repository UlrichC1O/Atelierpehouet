{{-- Scene · Art numérique — on a tablet, pixels and triangles assemble into an image while a scanline sweeps the screen. --}}
@php
    // A pixel triangle: rows of squares (8 px) forming the logo silhouette with its colour fields.
    $pixels = [];
    for ($row = 0; $row < 16; $row++) {
        $y = 98 + $row * 12;
        $half = ($row + 1) * 6;
        for ($x = 200 - $half; $x < 200 + $half; $x += 12) {
            $relX = ($x - (200 - $half)) / max(1, 2 * $half);
            $colour = $row < 7 ? ($relX < 0.32 && $row > 2 ? 'blue' : 'yellow') : ($row > 12 ? ($relX < 0.55 ? 'black' : 'red') : ($relX < 0.3 ? 'blue' : ($relX < 0.55 ? 'white' : 'red')));
            $pixels[] = ['x' => $x, 'y' => $y, 'c' => $colour, 'd' => round(($row * 0.09) + $relX * 0.4, 2)];
        }
    }
@endphp
<div class="scene scene--art-numerique ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="numerique__bg"/>
        <rect class="numerique__tablet" x="62" y="54" width="276" height="300" rx="18"/>
        <rect class="numerique__screen" x="78" y="70" width="244" height="252" rx="6"/>
        <circle class="numerique__button" cx="200" cy="338" r="6"/>

        <g class="numerique__pixels">
            @foreach ($pixels as $p)
                <rect class="numerique__px numerique__px--{{ $p['c'] }}" x="{{ $p['x'] }}" y="{{ $p['y'] }}" width="11" height="11" style="--d: {{ $p['d'] }}s"/>
            @endforeach
        </g>
        <rect class="numerique__scan" x="78" y="70" width="244" height="8"/>

        <g class="numerique__layers">
            <rect x="282" y="82" width="32" height="10" style="--d: 0s"/>
            <rect x="282" y="98" width="32" height="10" style="--d: .5s"/>
            <rect x="282" y="114" width="32" height="10" style="--d: 1s"/>
        </g>

        <g class="numerique__stylus">
            <path class="numerique__stylus-body" d="M0 0 6 -16 44 -98 54 -94 16 -12Z"/>
            <path class="numerique__stylus-tip" d="M0 0 6 -16 16 -12Z"/>
        </g>
    </svg>
</div>
