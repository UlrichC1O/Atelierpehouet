{{-- <x-counter :to="18" label="services" suffix="+" accent="red" /> — the final value is rendered server-side. --}}
@props([
    'to',
    'from' => 0,
    'prefix' => '',
    'suffix' => '',
    'label',
    'accent' => 'yellow',
])
<div {{ $attributes->class(['counter', 'accent-'.$accent]) }} data-reveal="zoom-in">
    <p class="counter__value"><span data-count-to="{{ $to }}" data-count-from="{{ $from }}" data-count-prefix="{{ $prefix }}" data-count-suffix="{{ $suffix }}">{{ $prefix }}{{ $to }}{{ $suffix }}</span></p>
    <p class="counter__label">{{ $label }}</p>
    <span class="counter__tri" aria-hidden="true"></span>
</div>
