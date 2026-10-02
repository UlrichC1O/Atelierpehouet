{{-- Scene · Mosaïque & vitrail — the panes of a pointed-arch window light up one by one as the sun passes, casting coloured rays. --}}
@php
    $state = 4242;
    $rand = function () use (&$state) {
        $state = ($state * 9301 + 49297) % 233280;

        return $state / 233280;
    };
    $cols = 6; $rows = 8; $x0 = 110; $x1 = 290; $y0 = 70; $y1 = 300;
    $pts = [];
    for ($j = 0; $j <= $rows; $j++) {
        for ($i = 0; $i <= $cols; $i++) {
            $jx = ($i > 0 && $i < $cols) ? ($rand() - 0.5) * 18 : 0;
            $jy = ($j > 0 && $j < $rows) ? ($rand() - 0.5) * 16 : 0;
            $pts[$j][$i] = [$x0 + ($x1 - $x0) * $i / $cols + $jx, $y0 + ($y1 - $y0) * $j / $rows + $jy];
        }
    }
    $palette = ['blue', 'yellow', 'red', 'blue', 'amber', 'red', 'white', 'yellow', 'blue-bright', 'orange'];
    $panes = [];
    for ($j = 0; $j < $rows; $j++) {
        for ($i = 0; $i < $cols; $i++) {
            [$a, $b, $c, $d] = [$pts[$j][$i], $pts[$j][$i + 1], $pts[$j + 1][$i + 1], $pts[$j + 1][$i]];
            $tris = $rand() < 0.5 ? [[$a, $b, $c], [$a, $c, $d]] : [[$a, $b, $d], [$b, $c, $d]];
            foreach ($tris as $t) {
                $cx = ($t[0][0] + $t[1][0] + $t[2][0]) / 3;
                $panes[] = [
                    'points' => implode(' ', array_map(fn ($p) => round($p[0], 1).','.round($p[1], 1), $t)),
                    'colour' => $palette[(int) floor($rand() * count($palette))],
                    'delay' => round(($cx - $x0) / ($x1 - $x0) * 2.6 + $rand() * 0.5, 2),
                ];
            }
        }
    }
@endphp
<div class="scene scene--mosaique-vitrail ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <clipPath id="mosaic-window"><path d="M110 300V160A118 118 0 0 1 200 66A118 118 0 0 1 290 160V300Z"/></clipPath>
            <linearGradient id="mosaic-ray" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#f8d449" stop-opacity=".45"/>
                <stop offset="100%" stop-color="#f8d449" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <rect width="400" height="400" class="mosaic__bg"/>
        <circle class="mosaic__sun" cx="60" cy="60" r="20"/>
        <rect class="mosaic__wall" x="80" y="40" width="240" height="280"/>

        <g class="mosaic__rays">
            <polygon points="118,300 162,300 200,384 120,384" style="--d: 0s"/>
            <polygon points="170,300 230,300 270,384 190,384" style="--d: .8s"/>
            <polygon points="238,300 282,300 340,384 262,384" style="--d: 1.6s"/>
        </g>

        <g clip-path="url(#mosaic-window)">
            <rect x="110" y="66" width="180" height="234" class="mosaic__glass-bg"/>
            @foreach ($panes as $pane)
                <polygon class="mosaic__pane mosaic__pane--{{ $pane['colour'] }}" style="--d: {{ $pane['delay'] }}s" points="{{ $pane['points'] }}"/>
            @endforeach
        </g>
        <path class="mosaic__frame" d="M110 300V160A118 118 0 0 1 200 66A118 118 0 0 1 290 160V300Z"/>
        <path class="mosaic__frame-light" d="M110 300V160A118 118 0 0 1 200 66A118 118 0 0 1 290 160V300Z"/>

        <g class="mosaic__tiles">
            @for ($k = 0; $k < 12; $k++)
                <polygon class="mosaic__tile mosaic__tile--{{ ['blue', 'yellow', 'red', 'white'][$k % 4] }}" style="--d: {{ $k * 0.18 }}s"
                         points="{{ $k % 2 ? (28 + $k * 29).',356 '.(57 + $k * 29).',356 '.(42.5 + $k * 29).',382' : (28 + $k * 29).',382 '.(57 + $k * 29).',382 '.(42.5 + $k * 29).',356' }}"/>
            @endfor
        </g>
    </svg>
</div>
