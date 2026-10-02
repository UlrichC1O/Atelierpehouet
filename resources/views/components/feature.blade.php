{{-- <x-feature icon="sparkle" title="…" text="…" :index="0" /> — the slot overrides the text. --}}
@props([
    'icon' => null,
    'title',
    'text' => null,
    'index' => 0,
])
<article {{ $attributes->class(['feature']) }} data-reveal="fade-up" style="--i: {{ $index }}">
    <span class="feature__num" aria-hidden="true">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
    <span class="feature__icon" aria-hidden="true"><x-icon :name="$icon ?? 'triangle'" /></span>
    <h3 class="feature__title">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="feature__text">{{ $slot }}</div>
    @elseif ($text)
        <p class="feature__text">{{ $text }}</p>
    @endif
</article>
