{{--
    Infinite ribbon of words separated by palette triangles.
    <x-marquee :items="['Fresque', 'Sculpture']" :reverse="true" speed="slow|normal|fast" />
    The sequence is rendered twice (the copy is aria-hidden) so the loop is seamless without JS.
--}}
@props([
    'items' => [],
    'reverse' => false,
    'speed' => 'normal',
])
@php($palette = ['blue', 'yellow', 'red', 'white', 'amber', 'orange'])
<div {{ $attributes->class(['marquee', 'marquee--reverse' => $reverse, 'marquee--'.$speed, 'ap-anim-scope']) }} data-marquee>
    <div class="marquee__track">
        @foreach ([false, true] as $copy)
            <div class="marquee__group" @if ($copy) aria-hidden="true" @endif>
                @foreach ($items as $item)
                    <span class="marquee__item">{{ $item }}</span>
                    <span class="marquee__sep marquee__sep--{{ $palette[$loop->index % count($palette)] }}" aria-hidden="true"></span>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
