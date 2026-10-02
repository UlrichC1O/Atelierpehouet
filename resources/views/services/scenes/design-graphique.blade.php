{{-- Scene · Design graphique — on a gridded artboard, a pen tool draws the logo triangle with Bézier nodes; swatches fill its fields. --}}
<div class="scene scene--design-graphique ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="design-grid" width="20" height="20" patternUnits="userSpaceOnUse">
                <path d="M20 0H0V20" fill="none" stroke="#1d1d23" stroke-width="1"/>
            </pattern>
        </defs>
        <rect width="400" height="400" class="design__bg"/>
        <rect x="40" y="40" width="320" height="320" fill="url(#design-grid)"/>
        <path class="design__rulers" d="M40 30H360M30 40V360"/>
        <rect class="design__artboard" x="40" y="40" width="320" height="320"/>

        <g class="design__fields" transform="translate(100 108) scale(2)">
            <polygon class="design__field design__field--yellow" style="--d: 0s" points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"/>
            <polygon class="design__field design__field--blue" style="--d: .35s" points="39.3,18.53 39.3,68.8 10.28,68.8"/>
            <polygon class="design__field design__field--red" style="--d: .7s" points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"/>
            <polygon class="design__field design__field--white" style="--d: 1.05s" points="39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8"/>
        </g>
        <path class="design__path" pathLength="1" d="M200 108 C230 160 262 214 300 281 L100 281 C130 228 168 165 200 108Z"/>
        <g class="design__handles">
            <path d="M200 108 L232 96M300 281 L320 300M100 281 L80 300"/>
            <circle cx="232" cy="96" r="4"/><circle cx="320" cy="300" r="4"/><circle cx="80" cy="300" r="4"/>
        </g>
        <g class="design__nodes">
            <rect x="194" y="102" width="12" height="12" style="--d: .2s"/>
            <rect x="294" y="275" width="12" height="12" style="--d: 1.2s"/>
            <rect x="94" y="275" width="12" height="12" style="--d: 2.2s"/>
        </g>
        <rect class="design__select" x="88" y="96" width="224" height="198" pathLength="1"/>

        <g class="design__swatches">
            <rect x="56" y="326" width="22" height="22" class="design__swatch design__swatch--blue"/>
            <rect x="84" y="326" width="22" height="22" class="design__swatch design__swatch--yellow"/>
            <rect x="112" y="326" width="22" height="22" class="design__swatch design__swatch--red"/>
            <rect x="140" y="326" width="22" height="22" class="design__swatch design__swatch--white"/>
        </g>

        <g class="design__pen">
            <path d="M0 0 9 -24 20 -16Z" class="design__pen-nib"/>
            <path d="M9 -24 20 -16 34 -40 23 -48Z" class="design__pen-body"/>
            <circle cx="9" cy="-12" r="2.2" class="design__pen-hole"/>
        </g>
    </svg>
</div>
