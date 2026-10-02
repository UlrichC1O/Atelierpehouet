{{-- Scene · Sérigraphie & impression — under a screen frame the squeegee passes; each pass adds a colour (blue, yellow, red) that rebuilds the triangle. --}}
<div class="scene scene--serigraphie-impression ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <rect width="400" height="400" class="serig__bg"/>
        <path class="serig__shirt" d="M136 88 166 74C176 92 224 92 234 74L264 88 312 120 290 160 266 146V346H134V146L110 160 88 120Z"/>
        <g class="serig__print" transform="translate(140 150) scale(1.2)">
            <polygon class="serig__layer serig__layer--blue" points="39.3,18.53 39.3,68.8 10.28,68.8 0,86.6 54.8,86.6 54.8,68.8"/>
            <polygon class="serig__layer serig__layer--yellow" points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"/>
            <polygon class="serig__layer serig__layer--red" points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"/>
            <polygon class="serig__key" points="50,0 100,86.6 0,86.6"/>
        </g>
        <g class="serig__marks">
            <path d="M120 140H136M128 132V148M264 140H280M272 132V148M120 268H136M128 260V276M264 268H280M272 260V276"/>
        </g>
        <rect class="serig__frame" x="100" y="120" width="200" height="176"/>
        <rect class="serig__mesh" x="108" y="128" width="184" height="160"/>
        <g class="serig__squeegee">
            <rect x="104" y="-6" width="192" height="12" rx="3" class="serig__blade"/>
            <rect x="140" y="-22" width="120" height="18" rx="5" class="serig__handle"/>
        </g>
    </svg>
</div>
