{{-- Scene · Calligraphie & lettrage — a nib writes a flowing word on guide lines; ink drops bloom and a capital turns gold. --}}
<div class="scene scene--calligraphie-lettrage ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="calli__bg"/>
        <path class="calli__guides" d="M36 150H364M36 210H364M36 270H364M36 300H364"/>
        <path class="calli__slant" d="M60 320 110 110M140 320 190 110M220 320 270 110M300 320 350 110"/>

        {{-- the gilded capital A (a triangle, of course) --}}
        <path class="calli__capital" pathLength="1" d="M56 280 L98 128 L140 280 M74 226 H124"/>
        {{-- "rt" in a flowing joined hand --}}
        <path class="calli__word" pathLength="1" d="M146 272 C150 250 155 230 160 214 C166 224 172 226 180 218 C186 212 194 212 200 218 C198 240 200 258 210 268 C220 278 236 270 242 250 C248 214 252 170 258 126 C256 176 254 228 258 262 C262 276 278 280 292 268 M232 186 H292"/>
        <path class="calli__flourish" pathLength="1" d="M292 268 C320 248 346 252 352 270 C358 292 318 306 270 300 C220 294 160 296 120 308"/>

        <g class="calli__drops">
            <circle cx="318" cy="324" r="7" style="--d: 0s"/>
            <circle cx="338" cy="340" r="4" style="--d: .3s"/>
            <circle cx="300" cy="346" r="3" style="--d: .55s"/>
        </g>

        <g class="calli__nib">
            <path class="calli__nib-shape" d="M0 0 -10 -26 0 -40 10 -26Z"/>
            <path class="calli__nib-slit" d="M0 0V-24"/>
            <circle cx="0" cy="-26" r="2.6" class="calli__nib-hole"/>
            <path class="calli__nib-holder" d="M-6 -40 -16 -120H16L6 -40Z"/>
        </g>
    </svg>
</div>
