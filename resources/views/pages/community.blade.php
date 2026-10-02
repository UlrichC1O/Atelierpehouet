{{-- Community: "L'art au service de la communauté" in practice. --}}
@extends('layouts.app')

@section('title', __('community.title'))
@section('meta_description', __('community.meta'))
@section('body_class', 'page-community')

@php
    $highlight = $services->whereIn('slug', ['cours-ateliers', 'art-communautaire', 'peinture-murale', 'decors-evenements'])->values();
    $communityService = $services->firstWhere('slug', 'art-communautaire');
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/community.css') }}">
    @if ($communityService && is_file(public_path('css/scenes/art-communautaire.css')))
        <link rel="stylesheet" href="{{ ap_asset('css/scenes/art-communautaire.css') }}">
    @endif
@endpush

@section('content')
    <x-page-hero :eyebrow="__('community.eyebrow')" :title="__('community.hero_title')" :lead="__('community.lead')"
                 :breadcrumbs="[['label' => __('ui.nav.community')]]" accent="orange">
        <x-button :href="route('contact')" icon="arrow-right" magnetic>{{ __('community.cta_propose') }}</x-button>
        @if ($communityService)
            <x-slot:aside class="community-hero-art">
                <div class="community-hero-art__stage">
                    @includeIf('services.scenes.art-communautaire', ['service' => $communityService])
                </div>
            </x-slot:aside>
        @endif
    </x-page-hero>

    <section class="section community-programmes">
        <div class="container">
            <x-section-heading :eyebrow="__('community.programmes_eyebrow')" :title="__('community.programmes_title')" accent="orange" />
            <div class="community-programmes__grid" data-reveal-stagger="90">
                @foreach ((array) __('community.programmes') as $programme)
                    <article class="community-programme accent-{{ ['blue', 'yellow', 'red', 'amber', 'orange', 'white'][$loop->index % 6] }}" data-reveal="fade-up">
                        <span class="community-programme__icon" aria-hidden="true"><x-icon :name="$programme['icon']" /></span>
                        <h3 class="community-programme__title">{{ $programme['title'] }}</h3>
                        <p class="community-programme__text">{{ $programme['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section--surface section--slant community-join">
        <div class="container">
            <x-section-heading :eyebrow="__('community.join_eyebrow')" :title="__('community.join_title')" align="center" accent="yellow" />
            <ol class="steps" role="list" data-inview>
                @foreach ((array) __('community.join') as $step)
                    <x-step :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :title="$step['title']" :text="$step['text']" />
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section community-for">
        <div class="container split">
            <x-section-heading :eyebrow="__('community.for_eyebrow')" :title="__('community.for_title')" accent="blue" />
            <ul class="community-for__cloud ap-anim-scope" role="list">
                @foreach ((array) __('community.for') as $audience)
                    <li class="community-for__tag" style="--i: {{ $loop->index }}" data-reveal="zoom-in">{{ $audience }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="community-quote">
        <x-slashes :count="4" />
        <div class="container">
            <blockquote class="community-quote__text" data-split="words" data-split-anim="glow">{{ __('community.quote') }}</blockquote>
        </div>
    </section>

    @if ($highlight->isNotEmpty())
        <section class="section community-services">
            <div class="container container--wide">
                <x-section-heading :eyebrow="__('community.services_eyebrow')" :title="__('community.services_title')" accent="red" />
                <div class="grid grid--4">
                    @foreach ($highlight as $service)
                        <x-service-card :service="$service" :index="$loop->index" variant="compact" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section section--surface community-faq">
        <div class="container container--narrow">
            <x-section-heading :title="__('community.faq_title')" accent="orange" />
            @foreach ((array) __('community.faq') as $item)
                <x-accordion-item :question="$item['q']" :open="$loop->first">{{ $item['a'] }}</x-accordion-item>
            @endforeach
        </div>
    </section>

    <x-cta-band :title="__('community.cta_title')" :text="__('community.cta_text')" :href="route('contact')" :button="__('community.cta_button')"
                :secondary-href="route('services.show', 'art-communautaire')" :secondary-button="__('community.cta_secondary')" />
@endsection
