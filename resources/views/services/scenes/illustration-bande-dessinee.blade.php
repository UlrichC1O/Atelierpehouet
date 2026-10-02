{{-- Scene · Illustration & bande dessinée — comic panels appear one by one, a pencil inks them and a speech bubble pops open with a triangle. --}}
<div class="scene scene--illustration-bande-dessinee ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="bd__bg"/>
        <rect class="bd__page" x="48" y="40" width="304" height="320"/>
        <g class="bd__panel" style="--d: 0s">
            <rect x="60" y="52" width="136" height="140" class="bd__frame"/>
            <path class="bd__ink" pathLength="1" d="M78 172 128 84 178 172Z"/>
            <circle cx="128" cy="140" r="12" class="bd__sun"/>
        </g>
        <g class="bd__panel" style="--d: .8s">
            <rect x="204" y="52" width="136" height="140" class="bd__frame"/>
            <path class="bd__ink" pathLength="1" d="M214 160C240 130 262 150 284 120C300 100 318 110 330 96"/>
            <rect x="230" y="150" width="34" height="34" class="bd__block bd__block--red"/>
            <rect x="268" y="134" width="44" height="50" class="bd__block bd__block--blue"/>
        </g>
        <g class="bd__panel" style="--d: 1.6s">
            <rect x="60" y="200" width="280" height="148" class="bd__frame"/>
            <path class="bd__ink" pathLength="1" d="M80 328H320M110 328V262L150 232L190 262V328M220 328V246H290V328"/>
            <path class="bd__lines" d="M76 214H120M76 222H104M300 214H326"/>
        </g>
        <g class="bd__bubble">
            <path d="M226 210C226 196 240 186 262 186H304C324 186 336 196 336 210V232C336 246 324 254 304 254H284L268 272L270 254H262C240 254 226 246 226 232Z" class="bd__bubble-shape"/>
            <polygon points="282,204 298,236 266,236" class="bd__bubble-tri"/>
        </g>
        <g class="bd__pencil">
            <path d="M0 0 8 -22 18 -18Z" class="bd__pencil-tip"/>
            <path d="M8 -22 18 -18 58 -110 48 -114Z" class="bd__pencil-body"/>
        </g>
    </svg>
</div>
