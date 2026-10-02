{{-- A rotating rosette of twelve triangles in the logo palette. <x-kaleidoscope size="s|m|l" /> --}}
@props([
    'size' => 'm',
])
@php($colors = ['blue', 'yellow', 'red', 'white', 'amber', 'orange'])
<div {{ $attributes->class(['kaleidoscope', 'kaleidoscope--'.$size, 'ap-anim-scope']) }} aria-hidden="true">
    <div class="kaleidoscope__wheel">
        @for ($i = 0; $i < 12; $i++)
            <span class="kaleidoscope__petal" style="--i: {{ $i }}; --c: var(--ap-{{ $colors[$i % 6] }})"></span>
        @endfor
    </div>
    <div class="kaleidoscope__wheel kaleidoscope__wheel--inner">
        @for ($i = 0; $i < 6; $i++)
            <span class="kaleidoscope__petal" style="--i: {{ $i * 2 }}; --c: var(--ap-{{ $colors[($i + 3) % 6] }})"></span>
        @endfor
    </div>
    <span class="kaleidoscope__core"></span>
</div>
