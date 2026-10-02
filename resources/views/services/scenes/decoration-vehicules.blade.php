{{-- Scene · Décoration de véhicules — a van drives past a horizon of triangles while stripes paint themselves on its body. --}}
<div class="scene scene--decoration-vehicules ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] ?? '' }}">
    <svg class="scene__svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
        <defs>
            <clipPath id="vehicule-body"><path d="M70 300V196C70 184 78 176 90 176H246L296 222H326C336 222 342 230 342 240V300Z"/></clipPath>
        </defs>
        <rect width="400" height="400" class="vehicule__bg"/>
        <g class="vehicule__horizon">
            <polygon points="0,250 60,160 120,250"/><polygon points="90,250 170,130 250,250"/><polygon points="220,250 300,150 380,250"/><polygon points="350,250 420,170 490,250"/>
            <polygon points="400,250 460,160 520,250"/><polygon points="490,250 570,130 650,250"/><polygon points="620,250 700,150 780,250"/>
        </g>
        <rect class="vehicule__road" y="318" width="400" height="82"/>
        <path class="vehicule__lane" d="M-40 360H440"/>
        <g class="vehicule__speed">
            <path d="M20 214H58M8 238H50M26 262H64" pathLength="1"/>
        </g>

        <g class="vehicule__van">
            <path class="vehicule__shell" d="M70 300V196C70 184 78 176 90 176H246L296 222H326C336 222 342 230 342 240V300Z"/>
            <g clip-path="url(#vehicule-body)">
                <rect class="vehicule__stripe vehicule__stripe--1" x="60" y="232" width="300" height="16"/>
                <rect class="vehicule__stripe vehicule__stripe--2" x="60" y="250" width="300" height="10"/>
                <rect class="vehicule__stripe vehicule__stripe--3" x="60" y="262" width="300" height="6"/>
                <polygon class="vehicule__tri" points="120,226 156,168 192,226"/>
            </g>
            <path class="vehicule__window" d="M252 186H238V218H286Z"/>
            <rect class="vehicule__window" x="96" y="188" width="122" height="26" rx="4"/>
            <text class="vehicule__name" x="210" y="282" text-anchor="middle">PEHOUET</text>
            <g class="vehicule__wheel" style="transform-origin: 130px 302px"><circle cx="130" cy="302" r="22"/><path d="M130 284V320M112 302H148"/></g>
            <g class="vehicule__wheel" style="transform-origin: 292px 302px"><circle cx="292" cy="302" r="22"/><path d="M292 284V320M274 302H310"/></g>
        </g>
    </svg>
</div>
