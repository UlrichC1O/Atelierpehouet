{{-- Scene · Portraits — a faceted bust lights up triangle by triangle inside a white frame. --}}
@php
    // [points, colour] — a low-poly head and shoulders in three-quarter view.
    $facets = [
        ['200,90 250,110 205,125', 'amber'], ['160,115 200,90 205,125', 'yellow'], ['250,110 262,165 238,160', 'orange'],
        ['205,125 250,110 238,160', 'amber'], ['160,115 205,125 185,165', 'orange'], ['150,170 160,115 185,165', 'blue-deep'],
        ['185,165 205,125 238,160', 'yellow'], ['238,160 262,165 248,215', 'amber'], ['185,165 238,160 225,200', 'orange'],
        ['225,200 238,160 248,215', 'red'], ['150,170 185,165 165,225', 'blue'], ['165,225 185,165 225,200', 'orange'],
        ['165,225 225,200 205,248', 'red'], ['225,200 248,215 205,248', 'amber'], ['182,246 228,240 230,282', 'red-deep'],
        ['182,246 230,282 180,282', 'blue-deep'], ['90,362 130,292 180,282', 'blue'], ['90,362 180,282 160,362', 'blue-deep'],
        ['160,362 180,282 205,332', 'red'], ['180,282 230,282 205,332', 'white'], ['205,332 230,282 252,362', 'red'],
        ['230,282 282,294 252,362', 'red-bright'], ['282,294 320,362 252,362', 'red-deep'],
    ];
@endphp
<div class="scene scene--portraits ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="portrait-glow" cx="50%" cy="45%" r="50%">
                <stop offset="0%" stop-color="#e3a94e" stop-opacity=".45"/>
                <stop offset="100%" stop-color="#e3a94e" stop-opacity="0"/>
            </radialGradient>
        </defs>
        <rect width="400" height="400" class="portrait__bg"/>
        <circle class="portrait__glow" cx="205" cy="190" r="170" fill="url(#portrait-glow)"/>
        <g class="portrait__bust">
            @foreach ($facets as $i => [$points, $colour])
                <polygon class="portrait__facet portrait__facet--{{ $colour }}" style="--i: {{ $i }}" points="{{ $points }}"/>
            @endforeach
            <polygon class="portrait__eye" points="221,154 238,151 231,161"/>
        </g>
        <rect class="portrait__frame" x="58" y="40" width="284" height="336" pathLength="1"/>
        <rect class="portrait__frame portrait__frame--inner" x="70" y="52" width="260" height="312" pathLength="1"/>
    </svg>
</div>
