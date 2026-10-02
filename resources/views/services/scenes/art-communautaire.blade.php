{{-- Scene · Art communautaire — figures made of triangles form a circle around a growing triangle; lines connect them and colour passes from one to the next. --}}
@php
    $people = [];
    $colours = ['blue', 'yellow', 'red', 'white', 'amber', 'orange', 'blue-bright', 'red-bright'];
    for ($k = 0; $k < 8; $k++) {
        $a = deg2rad(-90 + $k * 45);
        $people[] = ['x' => round(200 + 140 * cos($a), 1), 'y' => round(200 + 140 * sin($a), 1), 'c' => $colours[$k], 'd' => round($k * 0.45, 2), 'rot' => $k * 45];
    }
@endphp
<div class="scene scene--art-communautaire ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="commu__bg"/>
        <circle class="commu__ring" cx="200" cy="200" r="140" pathLength="1"/>
        <g class="commu__links">
            @foreach ($people as $k => $p)
                <path pathLength="1" style="--d: {{ $p['d'] }}s" d="M{{ $p['x'] }} {{ $p['y'] }} 200 200"/>
            @endforeach
        </g>
        <g class="commu__center">
            <polygon class="commu__tri commu__tri--yellow" points="200,140 252,230 148,230"/>
            <polygon class="commu__tri commu__tri--blue" points="174,185 200,230 148,230"/>
            <polygon class="commu__tri commu__tri--red" points="226,185 252,230 200,230"/>
            <polygon class="commu__tri-outline" points="200,140 252,230 148,230"/>
        </g>
        @foreach ($people as $p)
            <g class="commu__person commu__person--{{ $p['c'] }}" style="--d: {{ $p['d'] }}s; transform-origin: {{ $p['x'] }}px {{ $p['y'] }}px">
                <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] - 18 }}" r="11" class="commu__head"/>
                <polygon points="{{ $p['x'] }},{{ $p['y'] - 6 }} {{ $p['x'] + 20 }},{{ $p['y'] + 28 }} {{ $p['x'] - 20 }},{{ $p['y'] + 28 }}" class="commu__body"/>
            </g>
        @endforeach
        <g class="commu__rising">
            <polygon style="--d: 0s" points="196,120 204,120 200,113"/>
            <polygon style="--d: 1.2s" points="232,150 240,150 236,143"/>
            <polygon style="--d: 2.4s" points="162,154 170,154 166,147"/>
        </g>
    </svg>
</div>
