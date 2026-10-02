{{-- Twinkling white specks and tiny triangles. <x-starfield :count="40" :seed="2" /> --}}
@props([
    'count' => 40,
    'seed' => 1,
])
@php
    $state = (abs((int) $seed) * 4241 + 7) % 233280;
    $rand = function () use (&$state) {
        $state = ($state * 9301 + 49297) % 233280;

        return $state / 233280;
    };
@endphp
<div {{ $attributes->class(['starfield', 'ap-anim-scope']) }} aria-hidden="true">
    @for ($i = 0; $i < max(1, (int) $count); $i++)
        <span class="starfield__star @if ($rand() < 0.22) starfield__star--tri @endif"
              style="--x: {{ round($rand() * 100, 1) }}%; --y: {{ round($rand() * 100, 1) }}%; --s: {{ round(1 + $rand() * 3, 1) }}px; --d: {{ round(2 + $rand() * 4, 1) }}s; --delay: {{ round(-$rand() * 6, 1) }}s"></span>
    @endfor
</div>
