{{-- Scene · Maquillage artistique — on a face in profile, a brush paints coloured triangles that radiate like a sun around the eye. --}}
<div class="scene scene--maquillage-artistique ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="maquillage__bg"/>
        <g class="maquillage__rays">
            @for ($k = 0; $k < 12; $k++)
                <polygon points="226,168 {{ round(226 + 150 * cos(deg2rad($k * 30 - 4)), 1) }},{{ round(168 + 150 * sin(deg2rad($k * 30 - 4)), 1) }} {{ round(226 + 150 * cos(deg2rad($k * 30 + 4)), 1) }},{{ round(168 + 150 * sin(deg2rad($k * 30 + 4)), 1) }}"/>
            @endfor
        </g>
        {{-- the face in profile, looking right --}}
        <path class="maquillage__face" d="M150 380C150 330 120 300 120 240C120 150 170 92 236 92C290 92 316 132 316 170C316 182 326 196 334 210C340 220 330 228 318 228C320 238 316 246 310 250C316 258 312 268 302 272C304 292 290 304 268 302C252 300 244 312 244 330V380Z"/>
        <path class="maquillage__hair" d="M120 240C112 160 160 74 240 78C300 80 330 120 326 160C300 120 260 108 232 112C190 118 156 160 150 214C146 236 136 246 120 240Z"/>
        <g class="maquillage__paint">
            <polygon class="maquillage__tri maquillage__tri--yellow" style="--d: .2s" points="244,140 282,148 258,168"/>
            <polygon class="maquillage__tri maquillage__tri--red" style="--d: .6s" points="240,186 280,190 252,214"/>
            <polygon class="maquillage__tri maquillage__tri--blue" style="--d: 1s" points="214,150 236,176 206,182"/>
            <polygon class="maquillage__tri maquillage__tri--white" style="--d: 1.4s" points="262,206 290,214 268,232"/>
            <polygon class="maquillage__tri maquillage__tri--amber" style="--d: 1.8s" points="208,192 232,206 204,222"/>
        </g>
        <path class="maquillage__eye" d="M264 168C272 160 286 160 294 168C286 174 272 174 264 168Z"/>
        <g class="maquillage__sparkles">
            <path style="--d: 0s" d="M318 108l4 10 10 4-10 4-4 10-4-10-10-4 10-4z"/>
            <path style="--d: .8s" d="M180 70l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
            <path style="--d: 1.6s" d="M342 250l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
        </g>
        <g class="maquillage__brush">
            <path class="maquillage__brush-handle" d="M0 0 70 52"/>
            <path class="maquillage__brush-tip" d="M0 0C-8 -4 -14 -10 -16 -16C-8 -14 -2 -8 2 -2Z"/>
        </g>
    </svg>
</div>
