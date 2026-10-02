{{--
    Drifting triangles and Mondrian blocks behind content. Deterministic layout from the seed.
    <x-floating-shapes :count="12" variant="mixed|outline|solid" :seed="3" />
--}}
@props([
    'count' => 10,
    'variant' => 'mixed',
    'seed' => 1,
])
@php
    $state = (abs((int) $seed) * 7919 + 104729) % 233280;
    $rand = function () use (&$state) {
        $state = ($state * 9301 + 49297) % 233280;

        return $state / 233280;
    };
    $colors = ['blue', 'yellow', 'red', 'white', 'amber', 'orange'];
    $shapes = [];
    for ($i = 0; $i < max(1, (int) $count); $i++) {
        $kind = match ($variant) {
            'outline' => 'outline',
            'solid' => $rand() < 0.7 ? 'tri' : 'block',
            default => ['tri', 'outline', 'block', 'tri', 'bar'][(int) floor($rand() * 5)],
        };
        $shapes[] = [
            'kind' => $kind,
            'color' => $colors[(int) floor($rand() * count($colors))],
            'x' => round($rand() * 96, 1),
            'y' => round($rand() * 92, 1),
            's' => round(0.6 + $rand() * 2.6, 2),
            'd' => round(14 + $rand() * 16, 1),
            'delay' => round(-$rand() * 20, 1),
            'r' => (int) round($rand() * 360),
            'path' => 1 + (int) floor($rand() * 3),
        ];
    }
@endphp
<div {{ $attributes->class(['floating-shapes', 'ap-anim-scope']) }} aria-hidden="true">
    @foreach ($shapes as $shape)
        <span class="floating-shapes__item floating-shapes__item--{{ $shape['kind'] }} floating-shapes__item--p{{ $shape['path'] }}"
              style="--x: {{ $shape['x'] }}%; --y: {{ $shape['y'] }}%; --s: {{ $shape['s'] }}rem; --d: {{ $shape['d'] }}s; --delay: {{ $shape['delay'] }}s; --r: {{ $shape['r'] }}deg; --c: var(--ap-{{ $shape['color'] }})"></span>
    @endforeach
</div>
