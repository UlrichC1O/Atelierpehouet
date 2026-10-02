{{--
    The spiky white signature "Pehouet" of the logo, with its long diagonal slashes.
      <x-signature />                       static
      <x-signature :animated="true" />      draws itself stroke by stroke, then the slashes sweep
    Geometry traced from public/images/logo-ateliers-pehouet.png (viewBox = logo pixels,
    origin at 1040,195): a big angular P (steep stem, long top bar, pointed bowl whose return
    stroke runs far to the left), a small e, the two tall strokes of the h, "ouet" written along
    a rising baseline, two long thin slashes (≈ −21° and −25°) and four steep strokes (≈ 42°–55°).
    Every path has pathLength="1" so CSS can draw it (stroke-dashoffset 1 → 0).
    --d = delay and --t = duration of each stroke inside the drawing sequence (seconds).
    Decorative: the brand name is always given by the surrounding markup.
--}}
@props([
    'animated' => false,
])
<svg {{ $attributes->class(['signature', 'signature--animated' => $animated]) }} viewBox="0 0 800 410" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true" fill="none" stroke="#fafcfd" stroke-linecap="round" stroke-linejoin="round">
    <g class="signature__letters" stroke-width="4.4">
        {{-- P: steep stem, long top bar, pointed bowl returning far to the left --}}
        <path class="signature__path signature__path--letter" style="--d: 0s; --t: .4s" pathLength="1" d="M107 400 L341 190"/>
        <path class="signature__path signature__path--letter" style="--d: .32s; --t: .34s" pathLength="1" d="M200 212 L486 181"/>
        <path class="signature__path signature__path--letter" style="--d: .6s; --t: .36s" pathLength="1" d="M390 188 L300 240 L141 281"/>
        {{-- e --}}
        <path class="signature__path signature__path--letter" style="--d: .9s; --t: .3s" pathLength="1" d="M306 256 C290 262 272 272 268 282 C266 289 290 287 322 279"/>
        <path class="signature__path signature__path--letter" style="--d: 1.1s; --t: .14s" pathLength="1" d="M283 272 L309 265"/>
        {{-- o u e t, along the rising baseline --}}
        <path class="signature__path signature__path--letter" style="--d: 1.56s; --t: .26s" pathLength="1" d="M448 190 C434 186 420 204 425 214 C430 223 449 219 454 205 C457 196 455 191 448 190"/>
        <path class="signature__path signature__path--letter" style="--d: 1.74s; --t: .24s" pathLength="1" d="M481 166 L477 191 C476 199 487 201 494 196 C498 193 500 186 503 167 L499 196"/>
        <path class="signature__path signature__path--letter" style="--d: 1.9s; --t: .22s" pathLength="1" d="M511 172 L525 167 C528 160 520 154 515 158 C509 163 507 180 512 186 C516 190 522 186 526 182"/>
        <path class="signature__path signature__path--letter" style="--d: 2.04s; --t: .22s" pathLength="1" d="M537 143 L533 172 C532 180 540 181 546 175 L558 155"/>
    </g>
    <g class="signature__slashes" stroke-width="2.6">
        {{-- the two tall strokes of the h, then the tall stroke of the t --}}
        <path class="signature__path signature__path--steep" style="--d: 1.2s; --t: .3s" pathLength="1" d="M220 402 L488 89"/>
        <path class="signature__path signature__path--steep" style="--d: 1.36s; --t: .32s" pathLength="1" d="M288 357 L582 13"/>
        <path class="signature__path signature__path--steep" style="--d: 2.16s; --t: .3s" pathLength="1" d="M441 344 L616 95"/>
        {{-- the two long slashes sweeping across --}}
        <path class="signature__path signature__path--flat" style="--d: 2.3s; --t: .38s" pathLength="1" d="M15 327 L470 158"/>
        <path class="signature__path signature__path--flat" style="--d: 2.48s; --t: .42s" pathLength="1" d="M280 259 L792 22"/>
    </g>
    {{-- a spark of light that runs along the longest slash from time to time --}}
    <path class="signature__glint" pathLength="1" d="M280 259 L792 22"/>
</svg>
