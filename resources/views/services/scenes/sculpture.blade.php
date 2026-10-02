{{-- Scene · Sculpture — a CSS 3D pyramid with blue, yellow, red and white faces turns on a plinth while a chisel strikes and chips fly. --}}
<div class="scene scene--sculpture ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="sculpt-light" cx="50%" cy="40%" r="55%">
                <stop offset="0%" stop-color="#4f8edc" stop-opacity=".35"/>
                <stop offset="100%" stop-color="#4f8edc" stop-opacity="0"/>
            </radialGradient>
        </defs>
        <rect width="400" height="400" class="sculpt__bg"/>
        <circle cx="200" cy="170" r="190" fill="url(#sculpt-light)" class="sculpt__halo"/>
        <path class="sculpt__floor" d="M0 352H400"/>
        <g class="sculpt__plinth">
            <path class="sculpt__plinth-top" d="M118 286H282L300 300H100Z"/>
            <rect class="sculpt__plinth-body" x="100" y="300" width="200" height="52"/>
            <path class="sculpt__plinth-seam" d="M100 318H300"/>
        </g>
    </svg>
    <div class="sculpt__stage" aria-hidden="true">
        <div class="sculpt__pyramid">
            <span class="sculpt__face sculpt__face--front"></span>
            <span class="sculpt__face sculpt__face--right"></span>
            <span class="sculpt__face sculpt__face--back"></span>
            <span class="sculpt__face sculpt__face--left"></span>
        </div>
    </div>
    <svg class="scene__svg sculpt__fx" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <g class="sculpt__chips">
            <polygon points="0,-5 5,4 -5,4" style="--dx: 40px; --dy: -60px; --r: 200deg"/>
            <polygon points="0,-4 4,3 -4,3" style="--dx: 70px; --dy: -24px; --r: -160deg"/>
            <polygon points="0,-3 3,3 -3,3" style="--dx: 54px; --dy: -86px; --r: 300deg"/>
            <polygon points="0,-4 4,3 -4,3" style="--dx: 86px; --dy: -50px; --r: -240deg"/>
        </g>
        <g class="sculpt__tool">
            <g class="sculpt__chisel">
                <path class="sculpt__chisel-blade" d="M0 0 10 -4 54 -40 44 -48Z"/>
                <rect class="sculpt__chisel-handle" x="48" y="-78" width="16" height="44" rx="4" transform="rotate(50 56 -56)"/>
            </g>
            <g class="sculpt__hammer">
                <rect class="sculpt__hammer-head" x="70" y="-112" width="40" height="22" rx="4"/>
                <path class="sculpt__hammer-handle" d="M90 -90 116 -30"/>
            </g>
        </g>
    </svg>
</div>
