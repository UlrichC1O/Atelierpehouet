{{-- Small palette triangles orbiting a glowing centre. <x-orbit size="s|m|l" /> --}}
@props([
    'size' => 'm',
])
<div {{ $attributes->class(['orbit', 'orbit--'.$size, 'ap-anim-scope']) }} aria-hidden="true">
    <span class="orbit__core"></span>
    <span class="orbit__ring orbit__ring--1"><i></i></span>
    <span class="orbit__ring orbit__ring--2"><i></i><i></i></span>
    <span class="orbit__ring orbit__ring--3"><i></i><i></i><i></i></span>
</div>
