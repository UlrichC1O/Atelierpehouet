{{-- Scene · Tableaux sur commande — brush strokes paint a triangle composition on an easel, then the signature. --}}
<div class="scene scene--tableaux-sur-commande ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="tableau-spot" cx="50%" cy="38%" r="60%">
                <stop offset="0%" stop-color="#f8d449" stop-opacity=".22"/>
                <stop offset="100%" stop-color="#f8d449" stop-opacity="0"/>
            </radialGradient>
            <clipPath id="tableau-canvas"><rect x="118" y="78" width="164" height="196"/></clipPath>
        </defs>
        <rect width="400" height="400" class="tableau__bg"/>
        <rect width="400" height="400" fill="url(#tableau-spot)" class="tableau__spot"/>

        {{-- easel --}}
        <g class="tableau__easel">
            <path d="M200 44V78M134 380 186 60M266 380 214 60M200 380V290M108 296H292"/>
        </g>
        <rect class="tableau__canvas-edge" x="114" y="74" width="172" height="204" rx="2"/>
        <rect class="tableau__canvas" x="118" y="78" width="164" height="196"/>

        {{-- the painting, stroke by stroke --}}
        <g clip-path="url(#tableau-canvas)">
            <path class="tableau__stroke tableau__stroke--blue" style="--d: 0s" pathLength="1" d="M128 250 L165 150 L170 240"/>
            <path class="tableau__stroke tableau__stroke--yellow" style="--d: .7s" pathLength="1" d="M178 100 L230 118 L200 170 L250 175"/>
            <path class="tableau__stroke tableau__stroke--red" style="--d: 1.4s" pathLength="1" d="M228 200 L270 205 L240 262 L276 262"/>
            <path class="tableau__stroke tableau__stroke--white" style="--d: 2.1s" pathLength="1" d="M190 196 L210 196 L200 262"/>
            <path class="tableau__stroke tableau__stroke--ink" style="--d: 2.8s" pathLength="1" d="M128 262 L186 262"/>
            <polygon class="tableau__outline" style="--d: 3.4s" pathLength="1" points="200,96 270,262 130,262"/>
        </g>
        <path class="tableau__sign" pathLength="1" d="M226 252 L270 236 M240 258 L262 226 M248 256 L272 242"/>

        {{-- palette with paint dots --}}
        <g class="tableau__palette">
            <path d="M40 330C40 304 74 292 104 300C124 305 124 322 110 326C100 329 104 342 116 344C126 346 122 362 98 364C66 368 40 354 40 330Z"/>
            <circle class="tableau__dot tableau__dot--blue" style="--d: 0s" cx="62" cy="322" r="7"/>
            <circle class="tableau__dot tableau__dot--yellow" style="--d: .4s" cx="82" cy="310" r="7"/>
            <circle class="tableau__dot tableau__dot--red" style="--d: .8s" cx="100" cy="312" r="6"/>
            <circle class="tableau__dot tableau__dot--white" style="--d: 1.2s" cx="74" cy="348" r="6"/>
        </g>

        {{-- the brush --}}
        <g class="tableau__brush">
            <path class="tableau__brush-handle" d="M0 0 52 -68"/>
            <path class="tableau__brush-ferrule" d="M-3 4 4 -6 10 -1 3 9Z"/>
            <path class="tableau__brush-tip" d="M-6 8C-10 12-12 16-12 19C-8 18-3 15 1 11Z"/>
        </g>
    </svg>
</div>
