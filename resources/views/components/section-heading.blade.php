{{-- <x-section-heading eyebrow="…" title="…" lead="…" align="center" :level="2" accent="red" /> (slot = extra content) --}}
@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'align' => 'left',
    'level' => 2,
    'accent' => 'yellow',
])
@php($level = max(1, min(6, (int) $level)))
<header {{ $attributes->class(['section-heading', 'section-heading--center' => $align === 'center', 'accent-'.$accent]) }}>
    @if ($eyebrow)
        <p class="eyebrow" data-reveal="fade-up">{{ $eyebrow }}</p>
    @endif
    <h{{ $level }} class="section-heading__title" data-split="words" data-split-anim="rise">{{ $title }}</h{{ $level }}>
    @if ($lead)
        <p class="section-heading__lead lead" data-reveal="fade-up" data-reveal-delay="160">{{ $lead }}</p>
    @endif
    {{ $slot }}
</header>
