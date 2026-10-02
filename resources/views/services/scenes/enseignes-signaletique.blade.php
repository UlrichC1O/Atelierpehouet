{{-- Scene · Enseignes & signalétique — a triangular sign swings on its chains; a red neon word switches on letter by letter. --}}
@php
    // Letter positions from Audiowide's advance widths (em) at 54 px, with 0.06 em tracking, centred on 200.
    $advances = [['A', .78], ['T', .73], ['E', .76], ['L', .74], ['I', .28], ['E', .76], ['R', .83]];
    $size = 54;
    $total = (array_sum(array_column($advances, 1)) + 0.06 * (count($advances) - 1)) * $size;
    $x = 200 - $total / 2;
    $word = [];
    foreach ($advances as [$letter, $advance]) {
        $word[] = ['letter' => $letter, 'x' => round($x, 1)];
        $x += ($advance + 0.06) * $size;
    }
@endphp
<div class="scene scene--enseignes-signaletique ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <filter id="enseigne-neon" x="-30%" y="-30%" width="160%" height="160%">
                <feGaussianBlur in="SourceGraphic" stdDeviation="3" result="b"/>
                <feMerge><feMergeNode in="b"/><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
            </filter>
        </defs>
        <rect width="400" height="400" class="enseigne__bg"/>
        <rect x="0" y="0" width="22" height="400" class="enseigne__facade"/>
        <path class="enseigne__arm" d="M22 54H232M22 84 70 54"/>

        <g class="enseigne__sign">
            <path class="enseigne__chain" d="M88 54V92M206 54V92"/>
            <g transform="translate(66 92) scale(1.62)">
                <polygon fill="#f8d449" stroke="#fafcfd" stroke-width="1.2" points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"/>
                <polygon fill="#265fa5" stroke="#fafcfd" stroke-width="1.2" points="39.3,18.53 39.3,68.8 10.28,68.8"/>
                <polygon fill="#fafcfd" stroke="#fafcfd" stroke-width="1.2" points="39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8"/>
                <polygon fill="#b32c2b" stroke="#fafcfd" stroke-width="1.2" points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"/>
                <polygon fill="#000000" stroke="#fafcfd" stroke-width="1.2" points="10.28,68.8 54.8,68.8 54.8,86.6 0,86.6"/>
                <polygon fill="none" stroke="#fafcfd" stroke-width="2" points="50,0 100,86.6 0,86.6"/>
            </g>
        </g>

        <g class="enseigne__neon" filter="url(#enseigne-neon)">
            @foreach ($word as $i => $glyph)
                <text class="enseigne__letter" style="--i: {{ $i }}" x="{{ $glyph['x'] }}" y="330">{{ $glyph['letter'] }}</text>
            @endforeach
        </g>
        <path class="enseigne__tube" d="M44 352H356"/>

        <g class="enseigne__arrows">
            <path class="enseigne__arrow" style="--d: 0s" d="M290 150h44l14 14-14 14h-44Z"/>
            <path class="enseigne__arrow enseigne__arrow--alt" style="--d: .6s" d="M300 196h44l14 14-14 14h-44Z"/>
        </g>
    </svg>
</div>
