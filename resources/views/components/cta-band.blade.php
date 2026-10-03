{{--
    Closing call-to-action band: black canvas, sliding colour fields, giant outlined title, buttons.
    Rendering it tells the footer (rendered later) not to repeat its own call to action.
--}}
@props([
    'title',
    'text' => null,
    'href',
    'button',
    'secondaryHref' => null,
    'secondaryButton' => null,
])
@php(view()->share('apCtaBandShown', true))
<section {{ $attributes->class(['cta-band']) }}>
    <div class="cta-band__fields ap-anim-scope" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
    <div class="container cta-band__inner">
        <p class="cta-band__ghost" aria-hidden="true">{{ $title }}</p>
        <h2 class="cta-band__title" data-split="words" data-split-anim="rise">{{ $title }}</h2>
        @if ($text)
            <p class="cta-band__text" data-reveal="fade-up" data-reveal-delay="150">{{ $text }}</p>
        @endif
        <div class="cta-band__actions cluster" data-reveal="fade-up" data-reveal-delay="260">
            <x-button :href="$href" size="lg" icon="arrow-right" magnetic>{{ $button }}</x-button>
            @if ($secondaryHref && $secondaryButton)
                <x-button :href="$secondaryHref" variant="ghost" size="lg">{{ $secondaryButton }}</x-button>
            @endif
        </div>
    </div>
</section>
