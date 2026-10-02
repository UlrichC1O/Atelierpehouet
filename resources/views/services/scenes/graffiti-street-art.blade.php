{{-- Scene · Graffiti & street art — a shaking spray can tags "PEHOUET" on a dark brick wall, with drips and a glitch glow. --}}
<div class="scene scene--graffiti-street-art ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="graffiti-bricks" width="64" height="30" patternUnits="userSpaceOnUse">
                <rect width="64" height="30" fill="#121216"/>
                <path d="M0 1H64M0 16H64M32 1V16M1 16V30M64 16V30" fill="none" stroke="#000000" stroke-width="2"/>
            </pattern>
            <linearGradient id="graffiti-fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#4f8edc"/>
                <stop offset="55%" stop-color="#265fa5"/>
                <stop offset="100%" stop-color="#173d6b"/>
            </linearGradient>
        </defs>
        <rect width="400" height="400" fill="url(#graffiti-bricks)"/>
        <rect class="graffiti__floor" y="352" width="400" height="48"/>

        <g class="graffiti__tag">
            <text class="graffiti__glitch graffiti__glitch--red" x="200" y="222" text-anchor="middle">PEHOUET</text>
            <text class="graffiti__glitch graffiti__glitch--blue" x="200" y="222" text-anchor="middle">PEHOUET</text>
            <text class="graffiti__letters" x="200" y="222" text-anchor="middle">PEHOUET</text>
            <text class="graffiti__outline" x="200" y="222" text-anchor="middle">PEHOUET</text>
        </g>
        <g class="graffiti__drips">
            <rect x="74" y="222" width="5" height="34" rx="2.5" style="--d: .4s"/>
            <rect x="141" y="222" width="4" height="22" rx="2" style="--d: .9s"/>
            <rect x="203" y="222" width="5" height="42" rx="2.5" style="--d: 1.3s"/>
            <rect x="262" y="222" width="4" height="26" rx="2" style="--d: 1.8s"/>
            <rect x="318" y="222" width="5" height="36" rx="2.5" style="--d: 2.2s"/>
        </g>
        <g class="graffiti__slashes">
            <path d="M40 300 360 186" pathLength="1"/>
            <path d="M90 318 330 232" pathLength="1"/>
        </g>

        <g class="graffiti__can">
            <g class="graffiti__can-body">
                <rect x="-18" y="-10" width="36" height="78" rx="8" class="graffiti__can-shell"/>
                <rect x="-18" y="14" width="36" height="22" class="graffiti__can-band"/>
                <path d="M-10 -10V-22H10V-10" class="graffiti__can-cap"/>
                <rect x="-4" y="-30" width="8" height="9" rx="2" class="graffiti__can-nozzle"/>
            </g>
            <g class="graffiti__mist">
                <circle cx="-14" cy="-40" r="5" style="--d: 0s"/>
                <circle cx="-26" cy="-34" r="4" style="--d: .15s"/>
                <circle cx="-22" cy="-52" r="6" style="--d: .3s"/>
                <circle cx="-36" cy="-46" r="3.5" style="--d: .45s"/>
                <circle cx="-34" cy="-60" r="4.5" style="--d: .6s"/>
            </g>
        </g>
    </svg>
</div>
