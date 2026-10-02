{{-- Decorative separator: <x-tri-divider variant="zigzag|peak|slope" :flip="false" /> --}}
@props([
    'variant' => 'zigzag',
    'flip' => false,
])
<div {{ $attributes->class(['tri-divider', 'tri-divider--'.$variant, 'tri-divider--flip' => $flip, 'ap-anim-scope']) }} aria-hidden="true">
    @if ($variant === 'peak')
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none" focusable="false">
            <path class="tri-divider__line" d="M0 58 L600 4 L1200 58" pathLength="1"/>
            <path class="tri-divider__fill" d="M560 58 L600 22 L640 58 Z"/>
        </svg>
    @elseif ($variant === 'slope')
        <svg viewBox="0 0 1200 60" preserveAspectRatio="none" focusable="false">
            <path class="tri-divider__line" d="M0 58 L1200 2" pathLength="1"/>
            <path class="tri-divider__fill" d="M1080 58 L1200 2 L1200 58 Z"/>
        </svg>
    @else
        <svg viewBox="0 0 1200 40" preserveAspectRatio="none" focusable="false">
            <path class="tri-divider__line" d="M0 36 L50 4 L100 36 L150 4 L200 36 L250 4 L300 36 L350 4 L400 36 L450 4 L500 36 L550 4 L600 36 L650 4 L700 36 L750 4 L800 36 L850 4 L900 36 L950 4 L1000 36 L1050 4 L1100 36 L1150 4 L1200 36" pathLength="1"/>
        </svg>
        <span class="tri-divider__dots"><i></i><i></i><i></i></span>
    @endif
</div>
