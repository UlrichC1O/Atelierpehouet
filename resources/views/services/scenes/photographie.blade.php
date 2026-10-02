{{-- Scene · Photographie — six triangular aperture blades open onto a colourful composition; a soft flash, then an instant print slides out and develops. --}}
@php
    $blades = [];
    for ($k = 0; $k < 6; $k++) {
        $a = deg2rad($k * 60);
        $b = deg2rad($k * 60 + 60);
        $blades[] = [
            'points' => '200,170 '.round(200 + 140 * cos($a), 1).','.round(170 + 140 * sin($a), 1).' '.round(200 + 140 * cos($b), 1).','.round(170 + 140 * sin($b), 1),
            'dx' => round(70 * cos(deg2rad($k * 60 + 30)), 1),
            'dy' => round(70 * sin(deg2rad($k * 60 + 30)), 1),
        ];
    }
@endphp
<div class="scene scene--photographie ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <clipPath id="photo-lens"><circle cx="200" cy="170" r="120"/></clipPath>
        </defs>
        <rect width="400" height="400" class="photo__bg"/>
        <circle class="photo__body" cx="200" cy="170" r="140"/>
        <g clip-path="url(#photo-lens)">
            <rect x="80" y="50" width="240" height="240" class="photo__scene-bg"/>
            <polygon class="photo__view photo__view--yellow" points="200,70 290,226 110,226"/>
            <rect class="photo__view photo__view--blue" x="110" y="160" width="60" height="66"/>
            <rect class="photo__view photo__view--red" x="230" y="160" width="60" height="66"/>
            <circle class="photo__view photo__view--white" cx="200" cy="120" r="16"/>
            <g class="photo__blades">
                @foreach ($blades as $k => $blade)
                    <polygon class="photo__blade" style="--dx: {{ $blade['dx'] }}px; --dy: {{ $blade['dy'] }}px" points="{{ $blade['points'] }}"/>
                @endforeach
            </g>
        </g>
        <circle class="photo__ring" cx="200" cy="170" r="122"/>
        <circle class="photo__ring photo__ring--thin" cx="200" cy="170" r="132"/>
        <rect class="photo__flash" width="400" height="400"/>

        <g class="photo__print">
            <rect x="140" y="300" width="120" height="96" class="photo__print-paper"/>
            <rect x="150" y="308" width="100" height="70" class="photo__print-image"/>
            <polygon points="200,314 236,374 164,374" class="photo__print-tri"/>
        </g>
    </svg>
</div>
