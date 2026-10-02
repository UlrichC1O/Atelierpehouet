{{-- Scene · Décoration intérieure — in an isometric room the wall changes colour, frames slide into place, a lamp lights up and a rug unrolls. --}}
<div class="scene scene--decoration-interieure ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="deco-lamp" cx="50%" cy="20%" r="70%">
                <stop offset="0%" stop-color="#f8d449" stop-opacity=".55"/>
                <stop offset="100%" stop-color="#f8d449" stop-opacity="0"/>
            </radialGradient>
        </defs>
        <rect width="400" height="400" class="deco__bg"/>
        {{-- left wall, right wall, floor (isometric corner at x = 200) --}}
        <polygon class="deco__wall-left" points="40,90 200,40 200,250 40,300"/>
        <polygon class="deco__wall-right" points="200,40 360,90 360,300 200,250"/>
        <polygon class="deco__floor" points="40,300 200,250 360,300 200,370"/>
        <polygon class="deco__geo" points="200,40 280,65 200,150"/>
        <polygon class="deco__geo deco__geo--2" points="200,150 280,65 280,180"/>
        <path class="deco__edges" d="M200 40V250M40 300 200 250 360 300"/>

        {{-- frames on the left wall --}}
        <g class="deco__frame" style="--d: 0s">
            <polygon points="62,130 112,115 112,175 62,190" class="deco__frame-border"/>
            <polygon points="70,140 104,129 104,166 70,177" class="deco__frame-art deco__frame-art--blue"/>
        </g>
        <g class="deco__frame" style="--d: .4s">
            <polygon points="124,110 182,92 182,150 124,168" class="deco__frame-border"/>
            <polygon points="132,121 174,108 174,141 132,154" class="deco__frame-art deco__frame-art--yellow"/>
            <polygon points="153,115 174,141 132,154" class="deco__frame-art deco__frame-art--red"/>
        </g>

        {{-- lamp --}}
        <circle class="deco__glow" cx="300" cy="190" r="120" fill="url(#deco-lamp)"/>
        <path class="deco__lamp-stand" d="M300 290V196M286 292H314"/>
        <polygon class="deco__lamp-shade" points="282,196 318,196 308,168 292,168"/>

        {{-- rug & plant --}}
        <g class="deco__rug">
            <polygon class="deco__rug-base" points="110,312 200,284 290,312 200,342"/>
            <polygon class="deco__rug-tri" points="200,292 236,312 164,312"/>
            <polygon class="deco__rug-tri deco__rug-tri--b" points="164,316 236,316 200,334"/>
        </g>
        <g class="deco__plant">
            <path class="deco__pot" d="M74 300 80 272H104L110 300Z"/>
            <path class="deco__leaf" d="M92 272C80 248 76 230 86 214C96 232 96 252 92 272Z"/>
            <path class="deco__leaf deco__leaf--b" d="M92 272C98 246 110 234 124 232C118 248 106 262 92 272Z"/>
        </g>
    </svg>
</div>
