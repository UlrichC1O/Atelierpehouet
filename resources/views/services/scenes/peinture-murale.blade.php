{{-- Scene · Peinture murale & fresques — a roller sweeps across a black brick wall and paints the logo's triangle as a mural. --}}
<div class="scene scene--peinture-murale ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="mural-bricks" width="56" height="28" patternUnits="userSpaceOnUse">
                <path d="M0 .5H56M0 14.5H56M28 .5V14.5M.5 14.5V28M56 14.5V28" fill="none" stroke="#1d1d23" stroke-width="1.2"/>
            </pattern>
        </defs>
        <rect class="mural__wall" width="400" height="400"/>
        <rect width="400" height="346" fill="url(#mural-bricks)"/>
        <rect class="mural__ground" y="346" width="400" height="54"/>

        {{-- the mural: the logo's fields at 2.68× (apex 200,92 — base 66…334 at y 324) --}}
        <g class="mural__art">
            <g transform="translate(66 92) scale(2.68)">
                <polygon class="mural__f mural__f--yellow" points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"/>
                <polygon class="mural__f mural__f--blue" points="39.3,18.53 39.3,68.8 10.28,68.8"/>
                <polygon class="mural__f mural__f--white" points="39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8"/>
                <polygon class="mural__f mural__f--red" points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"/>
                <polygon class="mural__f mural__f--black" points="10.28,68.8 54.8,68.8 54.8,86.6 0,86.6"/>
            </g>
        </g>
        <g class="mural__seams" transform="translate(66 92) scale(2.68)">
            <polygon points="50,0 100,86.6 0,86.6" pathLength="1"/>
            <polyline points="39.3,18.53 39.3,68.8 54.8,68.8 54.8,86.6" pathLength="1"/>
            <polyline points="39.3,50.2 78.98,50.2" pathLength="1"/>
            <polyline points="59.3,50.2 59.3,86.6" pathLength="1"/>
        </g>
        <g class="mural__drips">
            <rect x="112" y="324" width="4" height="18" rx="2" class="mural__drip mural__drip--blue"/>
            <rect x="214" y="324" width="3.5" height="12" rx="2" class="mural__drip mural__drip--white"/>
            <rect x="282" y="324" width="4" height="22" rx="2" class="mural__drip mural__drip--red"/>
            <rect x="236" y="226" width="3" height="14" rx="1.5" class="mural__drip mural__drip--yellow"/>
        </g>

        {{-- ladder & paint pot --}}
        <g class="mural__ladder">
            <path d="M352 346 368 150M384 346 396 150M356 312H386M359 276H388M362 240H390M365 204H392M367 170H394"/>
        </g>
        <g class="mural__pot">
            <path d="M24 346 28 312H60L64 346Z"/>
            <path class="mural__pot-paint" d="M28 312H60V318H28Z"/>
        </g>

        {{-- the roller (drawn at the origin, positioned by CSS) --}}
        <g class="mural__roller">
            <rect class="mural__roller-cyl" x="-15" y="-44" width="30" height="88" rx="9"/>
            <rect class="mural__roller-shine" x="-9" y="-38" width="5" height="76" rx="2.5"/>
            <path class="mural__roller-arm" d="M15 0H26V58L44 96"/>
        </g>
    </svg>
</div>
