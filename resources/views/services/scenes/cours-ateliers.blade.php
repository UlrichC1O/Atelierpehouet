{{-- Scene · Cours & ateliers — on a gridded board, a pencil draws a triangle step by step while small triangle figures raise their hands. --}}
<div class="scene scene--cours-ateliers ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="cours-grid" width="16" height="16" patternUnits="userSpaceOnUse">
                <path d="M16 0H0V16" fill="none" stroke="#2c2c34" stroke-width="1"/>
            </pattern>
        </defs>
        <rect width="400" height="400" class="cours__bg"/>
        <rect class="cours__board" x="56" y="34" width="288" height="200" rx="6"/>
        <rect x="64" y="42" width="272" height="184" fill="url(#cours-grid)"/>
        <path class="cours__tray" d="M50 240H350"/>

        <g class="cours__dots">
            <circle cx="200" cy="66" r="4"/><circle cx="292" cy="206" r="4"/><circle cx="108" cy="206" r="4"/>
        </g>
        <path class="cours__line cours__line--1" pathLength="1" d="M200 66 292 206"/>
        <path class="cours__line cours__line--2" pathLength="1" d="M292 206 108 206"/>
        <path class="cours__line cours__line--3" pathLength="1" d="M108 206 200 66"/>
        <polygon class="cours__fill" points="200,92 270,196 130,196"/>

        <g class="cours__stars">
            <path style="--d: 0s" d="M90 70l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
            <path style="--d: .7s" d="M318 80l3 7 7 3-7 3-3 7-3-7-7-3 7-3z"/>
            <path style="--d: 1.4s" d="M300 150l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/>
        </g>

        <g class="cours__pupils">
            @foreach ([['x' => 80, 'c' => 'blue'], ['x' => 160, 'c' => 'yellow'], ['x' => 240, 'c' => 'red'], ['x' => 320, 'c' => 'white']] as $i => $pupil)
                <g class="cours__pupil cours__pupil--{{ $pupil['c'] }}" style="--d: {{ $i * 0.45 }}s">
                    <circle cx="{{ $pupil['x'] }}" cy="300" r="16" class="cours__head"/>
                    <polygon points="{{ $pupil['x'] }},318 {{ $pupil['x'] + 34 }},384 {{ $pupil['x'] - 34 }},384" class="cours__body"/>
                    <path class="cours__arm" d="M{{ $pupil['x'] + 14 }} 340 {{ $pupil['x'] + 30 }} 300"/>
                </g>
            @endforeach
        </g>

        <g class="cours__pencil">
            <path d="M0 0 6 -18 14 -14Z" class="cours__pencil-tip"/>
            <path d="M6 -18 14 -14 48 -88 40 -92Z" class="cours__pencil-body"/>
        </g>
    </svg>
</div>
