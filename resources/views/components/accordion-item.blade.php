{{-- <x-accordion-item question="…">answer</x-accordion-item> — native <details>, animated by effects.js. --}}
@props([
    'question',
    'open' => false,
])
<details {{ $attributes->class(['accordion']) }} @if ($open) open @endif>
    <summary class="accordion__summary">
        <span class="accordion__question">{{ $question }}</span>
        <span class="accordion__icon" aria-hidden="true"></span>
    </summary>
    <div class="accordion__panel">
        <div class="accordion__content">{{ $slot }}</div>
    </div>
</details>
