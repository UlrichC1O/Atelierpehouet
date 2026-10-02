{{--
    The triangle "A" of the logo: five Mondrian fields with white seams + the white outline
    (canonical geometry, docs/ARCHITECTURE.md §1).
      <x-logo-mark />                        static mark, role="img" named after the brand
      <x-logo-mark :animated="true" />       the five pieces fly in and assemble, then the outline draws
      <x-logo-mark :idle="true" />           subtle breathing loop (the pieces drift apart and back)
      <x-logo-mark decorative />             aria-hidden (when the brand name is given elsewhere)
    Fill/stroke attributes keep the mark correct even without CSS; 05-anim-brand.css animates it.
--}}
@props([
    'animated' => false,
    'idle' => false,
    'decorative' => false,
])
<svg {{ $attributes->class([
        'logo-mark',
        'logo-mark--animated' => $animated,
        'logo-mark--idle' => $idle,
    ]) }} viewBox="-3 -3 106 92.6" xmlns="http://www.w3.org/2000/svg" focusable="false" @if ($decorative) aria-hidden="true" @else role="img" aria-label="{{ config('atelier.name', 'Ateliers Pehouet') }}" @endif>
    <g class="logo-mark__pieces" stroke="#fafcfd" stroke-width="1" stroke-linejoin="round">
        <polygon class="logo-mark__piece logo-mark__piece--yellow" fill="#f8d449" points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"/>
        <polygon class="logo-mark__piece logo-mark__piece--blue" fill="#265fa5" points="39.3,18.53 39.3,68.8 10.28,68.8"/>
        <polygon class="logo-mark__piece logo-mark__piece--white" fill="#fafcfd" points="39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8"/>
        <polygon class="logo-mark__piece logo-mark__piece--red" fill="#b32c2b" points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"/>
        <polygon class="logo-mark__piece logo-mark__piece--black" fill="#000000" points="10.28,68.8 54.8,68.8 54.8,86.6 0,86.6"/>
    </g>
    <polygon class="logo-mark__outline" points="50,0 100,86.6 0,86.6" fill="none" stroke="#fafcfd" stroke-width="1.6" stroke-linejoin="round" pathLength="1"/>
</svg>
