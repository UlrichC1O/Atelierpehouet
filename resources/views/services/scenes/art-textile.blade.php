{{-- Scene · Art textile — a swallowtail banner ripples in the wind while colour bands paint themselves and a needle stitches a triangle. --}}
<div class="scene scene--art-textile ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <clipPath id="textile-banner"><path d="M106 86H294V318L200 274L106 318Z"/></clipPath>
        </defs>
        <rect width="400" height="400" class="textile__bg"/>
        <path class="textile__pole" d="M70 70H330"/>
        <circle class="textile__knob" cx="66" cy="70" r="8"/>
        <circle class="textile__knob" cx="334" cy="70" r="8"/>
        <path class="textile__cords" d="M120 70V86M280 70V86"/>

        <g class="textile__banner">
            <g clip-path="url(#textile-banner)">
                <rect x="100" y="80" width="200" height="250" class="textile__cloth"/>
                <rect x="100" y="104" width="200" height="34" class="textile__band textile__band--red" style="--d: 0s"/>
                <rect x="100" y="146" width="200" height="22" class="textile__band textile__band--yellow" style="--d: .5s"/>
                <rect x="100" y="176" width="200" height="14" class="textile__band textile__band--blue" style="--d: 1s"/>
                <g class="textile__folds">
                    <path d="M140 86V318M180 86V300M220 86V300M260 86V318"/>
                </g>
            </g>
            <path class="textile__hem" d="M106 86H294V318L200 274L106 318Z"/>
            <path class="textile__stitch" pathLength="1" d="M200 196 242 262H158Z"/>
        </g>

        <g class="textile__needle">
            <path class="textile__thread" d="M-30 40C-10 20 -4 10 0 0"/>
            <path class="textile__needle-body" d="M0 0 34 -34"/>
            <circle cx="30" cy="-30" r="2" class="textile__needle-eye"/>
        </g>
    </svg>
</div>
