{{-- Gallery of generative artworks (public/generated/gallery), filterable by style. --}}
@extends('layouts.app')

@section('title', __('gallery.title'))
@section('meta_description', __('gallery.meta'))
@section('body_class', 'page-gallery')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/gallery.css') }}">
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/gallery.js') }}" defer></script>
@endpush

@php
    $styleName = fn (string $key) => __('generator.styles.'.$key.'.name');
    $heroArt = array_slice($artworks, 0, 3);
@endphp

@section('content')
    <x-page-hero :eyebrow="__('gallery.eyebrow')" :title="__('gallery.hero_title')" :lead="__('gallery.lead')"
                 :breadcrumbs="[['label' => __('ui.nav.gallery')]]" accent="blue">
        <x-button :href="route('generator')" icon="sparkle" magnetic>{{ __('gallery.cta_create') }}</x-button>
        <x-button href="#oeuvres" variant="ghost" icon="arrow-down">{{ __('gallery.count', ['count' => count($artworks)]) }}</x-button>
        <x-slot:aside class="gallery-hero-stack">
            @foreach ($heroArt as $art)
                <img class="gallery-hero-stack__img" style="--i: {{ $loop->index }}" src="{{ $art['src'] }}" alt="" width="{{ $art['width'] }}" height="{{ $art['height'] }}" decoding="async">
            @endforeach
        </x-slot:aside>
    </x-page-hero>

    <section class="section gallery" id="oeuvres" data-filter-group data-filter-announce="{{ __('gallery.announce') }}">
        <div class="container container--wide">
            <div class="gallery__toolbar">
                <div class="filters" role="group" aria-label="{{ __('gallery.filter_label') }}">
                    <x-chip filter="all" :active="true">{{ __('gallery.all') }} <span class="chip__count">{{ count($artworks) }}</span></x-chip>
                    @foreach ($styles as $key)
                        <x-chip :filter="$key">{{ $styleName($key) }} <span class="chip__count">{{ collect($artworks)->where('style', $key)->count() }}</span></x-chip>
                    @endforeach
                </div>
                <button class="btn btn--ghost btn--sm gallery__shuffle" type="button" data-gallery-shuffle hidden>
                    <span class="btn__icon btn__icon--before"><x-icon name="dice" /></span><span class="btn__label">{{ __('gallery.shuffle') }}</span>
                </button>
            </div>

            <div class="gallery__grid" data-gallery-grid>
                @foreach ($artworks as $art)
                    <x-art-frame class="gallery__item gallery__item--{{ $loop->index % 5 === 0 ? 'tall' : 'square' }}"
                                 :src="$art['src']" :alt="$art['title']" :href="$art['src']" group="gallery" :style-key="$art['style']"
                                 :caption="__('gallery.caption', ['title' => $art['title'], 'style' => $styleName($art['style'])])"
                                 :width="$art['width']" :height="$art['height']" data-reveal="fade-up" style="--i: {{ $loop->index }}" />
                @endforeach
            </div>
            <p class="gallery__note muted">{{ __('gallery.note') }}</p>
        </div>
    </section>

    <section class="section section--surface gallery-engine">
        <div class="container split">
            <div class="stack" data-reveal="fade-right">
                <p class="eyebrow">{{ __('gallery.engine_eyebrow') }}</p>
                <h2 class="h2">{{ __('gallery.engine_title') }}</h2>
                <p class="lead">{{ __('gallery.engine_text') }}</p>
                <div><x-button :href="route('generator')" icon="sparkle">{{ __('gallery.engine_cta') }}</x-button></div>
            </div>
            <div class="gallery-engine__visual" data-reveal="zoom-in">
                <x-kaleidoscope size="l" />
                <span class="gallery-engine__code" aria-hidden="true">render("pehouet", "communauté")</span>
            </div>
        </div>
    </section>

    <x-cta-band :title="__('gallery.cta_title')" :text="__('gallery.cta_text')" :href="route('contact')" :button="__('gallery.cta_button')"
                :secondary-href="route('services.index')" :secondary-button="__('gallery.cta_secondary')" />
@endsection
