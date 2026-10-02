{{-- About: the atelier told through its logo. --}}
@extends('layouts.app')

@section('title', __('about.title'))
@section('meta_description', __('about.meta'))
@section('body_class', 'page-about')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/about.css') }}">
@endpush

@php($logo = (array) __('about.logo'))
@php($values = (array) __('about.values'))

@section('content')
    <x-page-hero :eyebrow="__('about.eyebrow')" :title="__('about.hero_title')" :lead="__('about.lead')"
                 :breadcrumbs="[['label' => __('ui.nav.about')]]" accent="yellow">
        <x-button :href="route('contact')" icon="arrow-right">{{ __('about.cta_button') }}</x-button>
        <x-button :href="route('services.index')" variant="ghost">{{ __('about.cta_secondary') }}</x-button>
        <x-slot:aside class="about-hero-art">
            <x-logo-mark class="about-hero-art__mark" animated idle decorative />
            <x-signature class="about-hero-art__signature" animated />
        </x-slot:aside>
    </x-page-hero>

    <section class="section about-logo">
        <div class="container">
            <x-section-heading :eyebrow="__('about.logo_eyebrow')" :title="__('about.logo_title')" align="center" accent="red" />
            <div class="about-logo__grid" data-reveal-stagger="120">
                <article class="about-part about-part--triangle" data-reveal="fade-up">
                    <svg class="about-part__visual" viewBox="-4 -4 108 95" aria-hidden="true" focusable="false"><polygon class="about-part__tri" points="50,0 100,86.6 0,86.6" pathLength="1"/></svg>
                    <h3 class="about-part__title">{{ $logo['triangle']['title'] ?? '' }}</h3>
                    <p class="about-part__text">{{ $logo['triangle']['text'] ?? '' }}</p>
                </article>
                <article class="about-part about-part--fields" data-reveal="fade-up">
                    <x-mondrian variant="a" class="about-part__visual about-part__mondrian" />
                    <h3 class="about-part__title">{{ $logo['fields']['title'] ?? '' }}</h3>
                    <p class="about-part__text">{{ $logo['fields']['text'] ?? '' }}</p>
                </article>
                <article class="about-part about-part--gradient" data-reveal="fade-up">
                    <p class="about-part__visual about-part__teliers" aria-hidden="true">TELIERS</p>
                    <h3 class="about-part__title">{{ $logo['gradient']['title'] ?? '' }}</h3>
                    <p class="about-part__text">{{ $logo['gradient']['text'] ?? '' }}</p>
                </article>
                <article class="about-part about-part--signature" data-reveal="fade-up">
                    <x-signature class="about-part__visual about-part__sig" />
                    <h3 class="about-part__title">{{ $logo['signature']['title'] ?? '' }}</h3>
                    <p class="about-part__text">{{ $logo['signature']['text'] ?? '' }}</p>
                </article>
            </div>
        </div>
    </section>

    <section class="section section--surface section--slant about-mission">
        <div class="container split">
            <div class="stack">
                <p class="eyebrow" data-reveal="fade-up">{{ __('about.mission_eyebrow') }}</p>
                <h2 class="h2" data-split="words">{{ __('about.mission_title') }}</h2>
                @foreach ((array) __('about.mission_text') as $paragraph)
                    <p class="lead" data-reveal="fade-up">{{ $paragraph }}</p>
                @endforeach
            </div>
            <div class="about-mission__art" data-reveal="rotate">
                <x-mondrian variant="c" />
                <x-orbit size="m" class="about-mission__orbit" />
            </div>
        </div>
    </section>

    <section class="section about-values">
        <div class="container">
            <x-section-heading :eyebrow="__('about.values_eyebrow')" :title="__('about.values_title')" accent="blue" />
            <div class="about-values__grid" data-reveal-stagger="100">
                @foreach (['blue', 'yellow', 'red', 'white', 'black'] as $key)
                    <article class="about-value about-value--{{ $key }}" data-reveal="curtain">
                        <span class="about-value__chip" aria-hidden="true"></span>
                        <h3 class="about-value__name">{{ $values[$key]['name'] ?? '' }}</h3>
                        <p class="about-value__text">{{ $values[$key]['text'] ?? '' }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section--surface about-method">
        <div class="container container--narrow">
            <x-section-heading :eyebrow="__('about.method_eyebrow')" :title="__('about.method_title')" accent="amber" />
            <ol class="about-timeline" role="list" data-inview>
                @foreach ((array) __('about.method') as $step)
                    <li class="about-timeline__item" data-reveal="fade-left">
                        <span class="about-timeline__node" aria-hidden="true">{{ $loop->iteration }}</span>
                        <h3 class="about-timeline__title">{{ $step['title'] }}</h3>
                        <p class="about-timeline__text">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section about-not">
        <div class="container split">
            <x-section-heading :eyebrow="__('about.not_eyebrow')" :title="__('about.not_title')" accent="red" />
            <ul class="about-not__list" role="list" data-reveal-stagger="120">
                @foreach ((array) __('about.not') as $item)
                    <li data-reveal="fade-left"><span class="about-not__text">{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    <x-cta-band :title="__('about.cta_title')" :text="__('about.cta_text')" :href="route('contact')" :button="__('about.cta_button')"
                :secondary-href="route('services.index')" :secondary-button="__('about.cta_secondary')" />
@endsection
