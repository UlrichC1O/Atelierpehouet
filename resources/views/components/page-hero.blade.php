{{--
    Inner-page hero: breadcrumbs, eyebrow, split title, lead, actions (default slot) and an optional
    visual (<x-slot:aside>). A Mondrian/triangle composition in the accent colour sits behind.
--}}
@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
    'breadcrumbs' => [],
    'accent' => 'red',
    'compact' => false,
])
<section {{ $attributes->class(['page-hero', 'page-hero--compact' => $compact, 'page-hero--has-aside' => isset($aside), 'accent-'.$accent]) }}>
    <div class="page-hero__decor ap-anim-scope" aria-hidden="true">
        <span class="page-hero__field page-hero__field--a"></span>
        <span class="page-hero__field page-hero__field--b"></span>
        <span class="page-hero__field page-hero__field--c"></span>
        <span class="page-hero__field page-hero__field--d"></span>
        <span class="page-hero__tri"></span>
        <span class="page-hero__slash page-hero__slash--1"></span>
        <span class="page-hero__slash page-hero__slash--2"></span>
    </div>
    <div class="container page-hero__inner">
        <div class="page-hero__content">
            @if ($breadcrumbs)
                <x-breadcrumbs :items="$breadcrumbs" />
            @endif
            @if ($eyebrow)
                <p class="eyebrow" data-reveal="fade-up">{{ $eyebrow }}</p>
            @endif
            <h1 class="page-hero__title" data-split="words" data-split-anim="rise">{{ $title }}</h1>
            @if ($lead)
                <p class="page-hero__lead lead" data-reveal="fade-up" data-reveal-delay="220">{{ $lead }}</p>
            @endif
            @if ($slot->isNotEmpty())
                <div class="page-hero__actions cluster" data-reveal="fade-up" data-reveal-delay="340">{{ $slot }}</div>
            @endif
        </div>
        @isset($aside)
            <div {{ $aside->attributes->class(['page-hero__aside']) }} data-reveal="zoom-in" data-reveal-delay="200">{{ $aside }}</div>
        @endisset
    </div>
</section>
