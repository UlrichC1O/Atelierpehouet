{{-- Scene · Restauration & encadrement — a grey veil is wiped from a painting to reveal vivid colours; frame corners slide in and an amber glint runs around. --}}
<div class="scene scene--restauration-encadrement ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="restau__bg"/>
        <g class="restau__painting">
            <rect x="110" y="100" width="180" height="200" class="restau__p restau__p--black"/>
            <rect x="110" y="100" width="70" height="120" class="restau__p restau__p--blue"/>
            <rect x="180" y="100" width="110" height="70" class="restau__p restau__p--yellow"/>
            <rect x="180" y="170" width="60" height="50" class="restau__p restau__p--white"/>
            <rect x="240" y="170" width="50" height="130" class="restau__p restau__p--red"/>
            <rect x="110" y="220" width="130" height="80" class="restau__p restau__p--ink"/>
            <polygon points="150,290 200,200 250,290" class="restau__p restau__p--tri"/>
        </g>
        <g class="restau__veil">
            <rect x="110" y="100" width="180" height="200" class="restau__dust"/>
            <path class="restau__cracks" d="M120 130 150 150 142 180 170 196M260 120 236 150 252 178M200 260 224 236 260 254 280 240M130 270 160 250"/>
        </g>
        <g class="restau__swab">
            <path class="restau__swab-stick" d="M0 0 60 -70"/>
            <ellipse class="restau__swab-cotton" cx="0" cy="0" rx="12" ry="8" transform="rotate(-50)"/>
        </g>

        <rect class="restau__frame" x="94" y="84" width="212" height="232" pathLength="1"/>
        <rect class="restau__glint" x="94" y="84" width="212" height="232" pathLength="1"/>
        <g class="restau__corners">
            <path class="restau__corner" style="--fx: -40px; --fy: -40px" d="M86 128V76H138"/>
            <path class="restau__corner" style="--fx: 40px; --fy: -40px" d="M262 76H314V128"/>
            <path class="restau__corner" style="--fx: 40px; --fy: 40px" d="M314 272V324H262"/>
            <path class="restau__corner" style="--fx: -40px; --fy: 40px" d="M138 324H86V272"/>
        </g>
    </svg>
</div>
