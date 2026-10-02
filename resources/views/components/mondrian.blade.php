{{--
    A Mondrian composition in the logo palette: white seams, black ground, fields that shift.
    <x-mondrian variant="a|b|c" :animated="true" />
--}}
@props([
    'variant' => 'a',
    'animated' => true,
])
@php
    $cells = [
        'a' => ['blue', 'black', 'yellow', 'white', 'red', 'black', 'white', 'yellow', 'black'],
        'b' => ['red', 'white', 'black', 'blue', 'yellow', 'white', 'black', 'red', 'white'],
        'c' => ['yellow', 'black', 'blue', 'white', 'black', 'red', 'white', 'blue', 'yellow'],
    ][$variant] ?? ['blue', 'black', 'yellow', 'white', 'red', 'black', 'white', 'yellow', 'black'];
@endphp
<div {{ $attributes->class(['mondrian', 'mondrian--'.$variant, 'mondrian--animated' => $animated, 'ap-anim-scope']) }} aria-hidden="true">
    @foreach ($cells as $i => $color)
        <span class="mondrian__cell mondrian__cell--{{ $color }}" style="--i: {{ $i }}"></span>
    @endforeach
</div>
