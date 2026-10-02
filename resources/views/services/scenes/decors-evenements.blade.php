{{-- Scene · Décors de scène & événements — curtains open, spotlights sweep and triangle confetti falls before a glowing triangle backdrop. --}}
@php
    $confetti = [];
    $state = 777;
    $rand = function () use (&$state) {
        $state = ($state * 9301 + 49297) % 233280;

        return $state / 233280;
    };
    for ($k = 0; $k < 18; $k++) {
        $confetti[] = ['x' => round(40 + $rand() * 320), 'd' => round($rand() * 4, 2), 'r' => round($rand() * 360), 'c' => ['blue', 'yellow', 'red', 'white', 'amber'][$k % 5], 'dur' => round(3.2 + $rand() * 2, 2)];
    }
@endphp
<div class="scene scene--decors-evenements ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="event-beam" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#fafcfd" stop-opacity=".55"/>
                <stop offset="100%" stop-color="#fafcfd" stop-opacity="0"/>
            </linearGradient>
            <linearGradient id="event-teliers" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#f8d449"/><stop offset="50%" stop-color="#c96338"/><stop offset="100%" stop-color="#b32c2b"/>
            </linearGradient>
        </defs>
        <rect width="400" height="400" class="event__bg"/>
        <polygon class="event__backdrop" points="200,70 312,264 88,264"/>
        <polygon class="event__backdrop-line" points="200,92 292,252 108,252"/>
        <rect class="event__stage" x="20" y="264" width="360" height="30"/>
        <path class="event__stage-edge" d="M20 264H380"/>

        <g class="event__beam event__beam--l"><polygon points="70,20 96,20 190,264 70,264" fill="url(#event-beam)"/></g>
        <g class="event__beam event__beam--r"><polygon points="304,20 330,20 330,264 210,264" fill="url(#event-beam)"/></g>

        <g class="event__confetti">
            @foreach ($confetti as $c)
                <polygon class="event__bit event__bit--{{ $c['c'] }}" points="0,-5 5,4 -5,4"
                         style="--x: {{ $c['x'] }}px; --d: {{ $c['d'] }}s; --r: {{ $c['r'] }}deg; --dur: {{ $c['dur'] }}s"/>
            @endforeach
        </g>

        <path class="event__valance" d="M0 0H400V34C380 44 360 44 340 34C320 44 300 44 280 34C260 44 240 44 220 34C200 44 180 44 160 34C140 44 120 44 100 34C80 44 60 44 40 34C20 44 0 44 0 34Z"/>
        <g class="event__curtain event__curtain--l">
            <path d="M0 30H200V264C170 270 150 250 130 268C110 250 90 270 70 260C50 270 30 252 0 268Z"/>
            <path class="event__curtain-folds" d="M40 30V262M90 30V262M140 30V262"/>
        </g>
        <g class="event__curtain event__curtain--r">
            <path d="M200 30H400V268C370 252 350 270 330 260C310 270 290 250 270 268C250 250 230 270 200 264Z"/>
            <path class="event__curtain-folds" d="M260 30V262M310 30V262M360 30V262"/>
        </g>
        <g class="event__crowd">
            @foreach ([40, 92, 144, 196, 248, 300, 352] as $i => $x)
                <g style="--d: {{ $i * 0.17 }}s" class="event__person"><circle cx="{{ $x }}" cy="334" r="14"/><path d="M{{ $x - 24 }} 400C{{ $x - 22 }} 366 {{ $x + 22 }} 366 {{ $x + 24 }} 400Z"/></g>
            @endforeach
        </g>
    </svg>
</div>
