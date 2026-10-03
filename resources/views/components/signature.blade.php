{{--
    The white signature "Pehouet" of the logo: calligraphic strokes with fine tips, its long
    hairline and steep slashes.
      <x-signature />                       static
      <x-signature :animated="true" />      writes itself stroke by stroke, then the hairline sweeps
    The exact shape is traced from the owner's reference drawing and defined once per page in
    partials/signature-sprite (#ap-signature, in layouts.app); viewBox units = logo pixels from
    (1040, 195), as the wordmark expects. The animated version reveals that shape through a mask of
    guide strokes, one per pen stroke in writing order; each guide has pathLength="1" so CSS can draw
    it (stroke-dashoffset 1 → 0). --d = delay and --t = duration inside the sequence (seconds).
    With animations off they collapse to their end state, so the whole signature shows.
    Decorative: the brand name is always given by the surrounding markup.
--}}
@props([
    'animated' => false,
])
@php
    $reveal = $animated ? 'ap-signature-reveal-'.\Illuminate\Support\Str::random(8) : null;
@endphp
<svg {{ $attributes->class(['signature', 'signature--animated' => $animated]) }} viewBox="0 0 800 410" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
    @if ($animated)
        <defs>
            <mask id="{{ $reveal }}" maskUnits="userSpaceOnUse" x="-20" y="-40" width="840" height="490">
                <g fill="none" stroke="#fff" stroke-linecap="round" stroke-linejoin="round">
                    {{-- P: stem, top bar, pointed bowl and the thick stroke it returns along --}}
                    <path class="signature__path" style="--d: 0s; --t: 0.4s" stroke-width="13" pathLength="1" d="M118 389 L333 200"/>
                    <path class="signature__path" style="--d: 0.32s; --t: 0.34s" stroke-width="16" pathLength="1" d="M219 210 L401 191"/>
                    <path class="signature__path" style="--d: 0.6s; --t: 0.4s" stroke-width="18" pathLength="1" d="M318 194 L370 188 L404 192 L359 213 L314 227 L256 242"/>
                    <path class="signature__path" style="--d: 0.78s; --t: 0.22s" stroke-width="18" pathLength="1" d="M302 246 L371 218 L434 181"/>
                    {{-- e --}}
                    <path class="signature__path" style="--d: 0.92s; --t: 0.3s" stroke-width="25" pathLength="1" d="M311 255 L283 263 L275 276 L290 286 L314 277"/>
                    {{-- the two tall strokes of the h --}}
                    <path class="signature__path" style="--d: 1.2s; --t: 0.3s" stroke-width="12" pathLength="1" d="M258 356 L498 82"/>
                    <path class="signature__path" style="--d: 1.36s; --t: 0.32s" stroke-width="19" pathLength="1" d="M336 301 L582 17"/>
                    {{-- "ouet" along its rising baseline --}}
                    <path class="signature__path" style="--d: 1.56s; --t: 0.62s" stroke-width="52" pathLength="1" d="M421 214 L506 170 L594 126"/>
                    {{-- the tall stroke of the t --}}
                    <path class="signature__path" style="--d: 2.16s; --t: 0.3s" stroke-width="12" pathLength="1" d="M434 344 L601 114"/>
                    {{-- the long hairline sweeping across --}}
                    <path class="signature__path" style="--d: 2.3s; --t: 0.6s" stroke-width="10" pathLength="1" d="M32 320 L295 227 L769 44"/>
                </g>
            </mask>
        </defs>
    @endif
    <g class="signature__ink">
        <use href="#ap-signature" width="800" height="410" @if ($animated) mask="url(#{{ $reveal }})" @endif />
    </g>
    {{-- a spark of light that runs along the long hairline from time to time --}}
    <path class="signature__glint" pathLength="1" d="M295 227 L765 46"/>
</svg>
