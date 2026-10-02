{{-- Slow blue / yellow / red glows behind content. <x-aurora intensity="soft|normal|strong" /> --}}
@props([
    'intensity' => 'normal',
])
<div {{ $attributes->class(['aurora', 'aurora--'.$intensity, 'ap-anim-scope']) }} aria-hidden="true">
    <span class="aurora__glow aurora__glow--blue"></span>
    <span class="aurora__glow aurora__glow--yellow"></span>
    <span class="aurora__glow aurora__glow--red"></span>
</div>
