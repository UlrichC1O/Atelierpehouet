{{-- Home: the front door of the atelier. --}}
@extends('layouts.app')

@section('meta_description', __('home.meta'))
@section('body_class', 'page-home')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/home.css') }}">
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/hero.js') }}" defer></script>
@endpush

@php
    $colours = (array) __('home.manifesto.colours');
    $cycleStyles = array_values($artStyles);
@endphp

@section('content')
    {{-- 1 · Hero --}}
    <section class="home-hero ap-anim-scope" aria-labelledby="home-title">
        <canvas class="home-hero__canvas" data-hero-canvas aria-hidden="true"></canvas>
        <x-aurora intensity="strong" />
        <x-floating-shapes :count="12" :seed="3" />
        <x-slashes :count="5" class="home-hero__slashes" />
        <div class="container container--wide home-hero__inner">
            <h1 id="home-title" class="home-hero__title">
                <x-logo-wordmark size="hero" animated tagline signature />
            </h1>
            <p class="home-hero__type" data-reveal="fade-up" data-reveal-delay="2600">
                <span class="home-hero__prefix">{{ __('home.hero.prefix') }}</span>
                <span class="home-hero__word" data-typewriter='@json(array_values((array) __('home.hero.words')))'>{{ __('home.hero.words')[0] }}</span>
            </p>
            <div class="cluster home-hero__actions" data-reveal="fade-up" data-reveal-delay="2900">
                <x-button :href="route('services.index')" size="lg" icon="arrow-right" magnetic>{{ __('home.hero.cta_services') }}</x-button>
                <x-button :href="route('generator')" variant="ghost" size="lg" icon-before="sparkle">{{ __('home.hero.cta_create') }}</x-button>
            </div>
        </div>
        <a class="home-hero__scroll" href="#manifeste">
            <span class="visually-hidden">{{ __('home.hero.scroll') }}</span>
            <span class="home-hero__scroll-tri" aria-hidden="true"></span>
        </a>
    </section>

    {{-- 2 · Marquees --}}
    <div class="home-marquees" aria-hidden="true">
        <x-marquee :items="$services->pluck('title')->all()" />
        <x-marquee :items="$services->pluck('title')->reverse()->values()->all()" reverse speed="slow" />
    </div>

    {{-- 3 · Manifesto --}}
    <section class="section home-manifesto" id="manifeste">
        <div class="container">
            <p class="eyebrow" data-reveal="fade-up">{{ __('home.manifesto.eyebrow') }}</p>
            <p class="home-manifesto__statement" data-split="words" data-split-anim="rise">{{ __('home.manifesto.statement') }}</p>
            <p class="lead" data-reveal="fade-up">{{ __('home.manifesto.text') }}</p>
            <div class="home-colours" data-reveal-stagger="110">
                @foreach (['blue', 'yellow', 'red', 'white', 'black'] as $key)
                    <article class="home-colour home-colour--{{ $key }}" data-reveal="mondrian" tabindex="0">
                        <span class="home-colour__swatch" aria-hidden="true"></span>
                        <h3 class="home-colour__name">{{ $colours[$key]['name'] ?? $key }}</h3>
                        <p class="home-colour__meaning">{{ $colours[$key]['meaning'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 4 · Services --}}
    <section class="section section--surface section--slant home-services" data-filter-group data-filter-announce="{{ __('home.services.announce') }}">
        <div class="container container--wide">
            <div class="home-services__head">
                <x-section-heading :eyebrow="__('home.services.eyebrow')" :title="__('home.services.title', ['count' => $services->count()])" :lead="__('home.services.lead')" accent="red" />
                <div class="filters" role="group" aria-label="{{ __('home.services.filter_label') }}">
                    <x-chip filter="all" :active="true">{{ __('components.filter.all') }} <span class="chip__count">{{ $services->count() }}</span></x-chip>
                    @foreach ($categories as $key => $label)
                        <x-chip :filter="$key" class="accent-{{ config('atelier.categories.'.$key.'.accent', 'yellow') }}">{{ $label }}</x-chip>
                    @endforeach
                </div>
            </div>
            <div class="home-services__grid">
                @foreach ($services as $service)
                    <x-service-card :service="$service" :index="$loop->index" :variant="in_array($loop->index, [0, 9, 17], true) ? 'feature' : 'default'"
                                    class="{{ in_array($loop->index, [0, 9, 17], true) ? 'home-services__wide' : '' }}" />
                @endforeach
            </div>
            <p class="home-services__all">
                <x-button :href="route('services.index')" variant="outline" icon="arrow-right" class="accent-yellow">{{ __('home.services.all') }}</x-button>
            </p>
        </div>
    </section>

    {{-- 5 · Process --}}
    <section class="section home-process">
        <div class="container">
            <x-section-heading :eyebrow="__('home.process.eyebrow')" :title="__('home.process.title')" align="center" accent="blue" />
            <div class="home-process__stage" data-inview>
                <svg class="home-process__tri" viewBox="0 0 600 520" aria-hidden="true" focusable="false">
                    <polygon class="home-process__path" points="300,20 580,500 20,500" pathLength="1"/>
                    <polygon class="home-process__path home-process__path--inner" points="300,140 470,440 130,440" pathLength="1"/>
                </svg>
                <ol class="home-process__steps" role="list">
                    @foreach ((array) __('home.process.steps') as $step)
                        <li class="home-process__step home-process__step--{{ $loop->iteration }}" data-reveal="zoom-in" style="--reveal-delay: {{ 300 + $loop->index * 250 }}ms">
                            <span class="home-process__num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="home-process__title">{{ $step['title'] }}</h3>
                            <p class="home-process__text">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- 6 · Numbers (all derived from the site itself) --}}
    <section class="section section--tight home-numbers">
        <div class="container">
            <x-section-heading :eyebrow="__('home.numbers.eyebrow')" :title="__('home.numbers.title')" accent="yellow" />
            <div class="home-numbers__grid">
                <x-counter :to="$services->count()" :label="__('home.numbers.services')" accent="red" />
                <x-counter :to="count($categories)" :label="__('home.numbers.families')" accent="yellow" />
                <x-counter :to="5" :label="__('home.numbers.colours')" accent="blue" />
                <x-counter :to="$animationCount" :label="__('home.numbers.animations')" accent="amber" />
                <x-counter :to="1" :label="__('home.numbers.triangle')" accent="white" />
            </div>
        </div>
    </section>

    {{-- 7 · Generator teaser --}}
    <section class="section section--surface home-generator">
        <div class="container split split--reverse">
            <div class="home-generator__visual" data-reveal="tri">
                <div class="home-generator__frame">
                    <img class="home-generator__img" data-art-cycle data-styles='@json($cycleStyles)' data-url="{{ route('generator.art') }}" data-seed="Pehouet"
                         data-alt="{{ __('home.generator.alt', ['style' => '__STYLE__']) }}"
                         src="{{ asset('generated/gallery/'.($cycleStyles[0] ?? 'pehouet').'-1.svg') }}" width="800" height="800"
                         alt="{{ __('home.generator.alt', ['style' => __('generator.styles.'.($cycleStyles[0] ?? 'pehouet').'.name')]) }}" loading="lazy" decoding="async">
                </div>
                <x-orbit size="s" class="home-generator__orbit" />
            </div>
            <div class="stack" data-reveal="fade-left">
                <p class="eyebrow">{{ __('home.generator.eyebrow') }}</p>
                <h2 class="h2 gradient-text">{{ __('home.generator.title') }}</h2>
                <p class="lead">{{ __('home.generator.text') }}</p>
                <div><x-button :href="route('generator')" icon="sparkle" magnetic data-burst>{{ __('home.generator.cta') }}</x-button></div>
            </div>
        </div>
    </section>

    {{-- 8 · Gallery teaser --}}
    <section class="section home-gallery">
        <div class="container container--wide">
            <div class="home-gallery__head">
                <x-section-heading :eyebrow="__('home.gallery.eyebrow')" :title="__('home.gallery.title')" :lead="__('home.gallery.text')" accent="blue" />
                <x-button :href="route('gallery')" variant="ghost" icon="arrow-right">{{ __('home.gallery.cta') }}</x-button>
            </div>
        </div>
        <div class="home-gallery__strip ap-anim-scope">
            <div class="home-gallery__track">
                @foreach ($galleryPreview as $art)
                    <x-art-frame class="home-gallery__item" :src="$art['src']" :alt="$art['title']" :href="$art['src']" group="home"
                                 :caption="$art['title']" :width="$art['width']" :height="$art['height']" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- 9 · Community --}}
    <section class="section section--surface home-community">
        <div class="container split">
            <div class="stack" data-reveal="fade-right">
                <p class="eyebrow">{{ __('home.community.eyebrow') }}</p>
                <h2 class="h2">{{ __('home.community.title') }}</h2>
                <p class="lead">{{ __('home.community.text') }}</p>
                <blockquote class="home-community__quote"><p>{{ __('home.community.quote') }}</p></blockquote>
                <div><x-button :href="route('community')" variant="secondary" icon="arrow-right">{{ __('home.community.cta') }}</x-button></div>
            </div>
            <div class="home-community__visual" data-reveal="zoom-in">
                <x-kaleidoscope size="l" />
                <x-logo-mark class="home-community__mark" idle decorative />
            </div>
        </div>
    </section>

    <x-cta-band :title="__('home.cta.title')" :text="__('home.cta.text')" :href="route('contact')" :button="__('home.cta.button')"
                :secondary-href="route('services.index')" :secondary-button="__('home.cta.secondary')" />
@endsection
