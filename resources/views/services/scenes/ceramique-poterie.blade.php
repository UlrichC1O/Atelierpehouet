{{-- Scene · Céramique & poterie — a potter's wheel spins, a vase rises and its glaze bands shift through the palette. --}}
<div class="scene scene--ceramique-poterie ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <radialGradient id="ceram-kiln" cx="50%" cy="62%" r="60%">
                <stop offset="0%" stop-color="#c96338" stop-opacity=".55"/>
                <stop offset="60%" stop-color="#b32c2b" stop-opacity=".15"/>
                <stop offset="100%" stop-color="#b32c2b" stop-opacity="0"/>
            </radialGradient>
            <clipPath id="ceram-vase"><path d="M160 318C146 290 138 258 156 228C170 206 174 188 170 166H230C226 188 230 206 244 228C262 258 254 290 240 318Z"/></clipPath>
        </defs>
        <rect width="400" height="400" class="ceram__bg"/>
        <rect width="400" height="400" fill="url(#ceram-kiln)" class="ceram__kiln"/>

        <ellipse class="ceram__wheel" cx="200" cy="326" rx="132" ry="28"/>
        <ellipse class="ceram__wheel-marks" cx="200" cy="326" rx="104" ry="20" pathLength="1"/>
        <rect class="ceram__stand" x="186" y="350" width="28" height="50"/>

        <g class="ceram__vase">
            <g clip-path="url(#ceram-vase)">
                <rect class="ceram__band ceram__band--1" x="130" y="160" width="140" height="36"/>
                <rect class="ceram__band ceram__band--2" x="130" y="196" width="140" height="44"/>
                <rect class="ceram__band ceram__band--3" x="130" y="240" width="140" height="40"/>
                <rect class="ceram__band ceram__band--4" x="130" y="280" width="140" height="40"/>
                <g class="ceram__turn">
                    <path d="M120 170 160 330M150 170 190 330M180 170 220 330M210 170 250 330M240 170 280 330M270 170 310 330" />
                </g>
            </g>
            <path class="ceram__outline" d="M160 318C146 290 138 258 156 228C170 206 174 188 170 166H230C226 188 230 206 244 228C262 258 254 290 240 318Z"/>
            <ellipse class="ceram__mouth" cx="200" cy="166" rx="30" ry="6"/>
        </g>

        <g class="ceram__specks">
            <circle cx="120" cy="296" r="3" style="--d: 0s; --dx: -30px"/>
            <circle cx="282" cy="300" r="2.5" style="--d: .5s; --dx: 30px"/>
            <circle cx="110" cy="310" r="2" style="--d: 1s; --dx: -40px"/>
            <circle cx="292" cy="312" r="3" style="--d: 1.5s; --dx: 36px"/>
        </g>
    </svg>
</div>
