{{-- One step of a process, used inside <ol class="steps">. --}}
@props([
    'number',
    'title',
    'text' => null,
])
<li {{ $attributes->class(['step']) }} data-reveal="fade-up">
    <span class="step__num" aria-hidden="true"><span>{{ $number }}</span></span>
    <h3 class="step__title">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="step__text">{{ $slot }}</div>
    @elseif ($text)
        <p class="step__text">{{ $text }}</p>
    @endif
</li>
