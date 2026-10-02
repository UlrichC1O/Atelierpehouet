{{-- Services index: every art service, filterable by family. --}}
@extends('layouts.app')

@section('title', __('services.index.title'))
@section('meta_description', __('services.index.meta'))
@section('body_class', 'page-services')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/services.css') }}">
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/services.js') }}" defer></script>
@endpush

@section('content')
    <x-page-hero
        :eyebrow="__('services.index.eyebrow', ['count' => $services->count()])"
        :title="__('services.index.hero_title')"
        :lead="__('services.index.lead')"
        :breadcrumbs="[['label' => __('ui.nav.services')]]"
        accent="yellow">
        <x-button href="#catalogue" icon="arrow-down">{{ __('services.index.cta_browse') }}</x-button>
        <x-button :href="route('contact')" variant="ghost">{{ __('ui.cta.quote') }}</x-button>
        <x-slot:aside class="services-collage">
            <x-mondrian variant="b" class="services-collage__mondrian" />
            <x-kaleidoscope size="s" class="services-collage__kaleido" />
            <x-orbit size="s" class="services-collage__orbit" />
            <span class="services-collage__count" aria-hidden="true">{{ $services->count() }}</span>
        </x-slot:aside>
    </x-page-hero>

    <section class="section" id="catalogue" data-filter-group data-filter-announce="{{ __('services.index.announce') }}">
        <div class="container container--wide">
            <div class="services-toolbar">
                <x-section-heading :eyebrow="__('services.index.catalogue_eyebrow')" :title="__('services.index.catalogue_title')" accent="red" />
                <div class="filters" role="group" aria-label="{{ __('services.index.filter_label') }}">
                    <x-chip filter="all" :active="true">{{ __('components.filter.all') }} <span class="chip__count">{{ $services->count() }}</span></x-chip>
                    @foreach ($categories as $key => $label)
                        <x-chip :filter="$key" class="accent-{{ config('atelier.categories.'.$key.'.accent', 'yellow') }}">{{ $label }} <span class="chip__count">{{ $services->where('category', $key)->count() }}</span></x-chip>
                    @endforeach
                </div>
            </div>

            <div class="services-grid">
                @foreach ($services as $service)
                    <x-service-card :service="$service" :index="$loop->index" :variant="$loop->index % 7 === 0 ? 'feature' : 'default'"
                                    class="{{ $loop->index % 7 === 0 ? 'services-grid__wide' : '' }}" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="section section--surface section--slant services-method">
        <div class="container">
            <x-section-heading :eyebrow="__('services.index.how_eyebrow')" :title="__('services.index.how_title')" :lead="__('services.index.how_lead')" align="center" accent="blue" />
            <ol class="steps" role="list" data-inview>
                @foreach (__('services.index.steps') as $step)
                    <x-step :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :title="$step['title']" :text="$step['text']" />
                @endforeach
            </ol>
        </div>
    </section>

    <section class="section services-bridge">
        <div class="container split">
            <div class="stack" data-reveal="fade-right">
                <p class="eyebrow">{{ __('services.index.bridge_eyebrow') }}</p>
                <h2 class="h2">{{ __('services.index.bridge_title') }}</h2>
                <p class="lead">{{ __('services.index.bridge_text') }}</p>
                <div class="cluster">
                    <x-button :href="route('generator')" icon="sparkle">{{ __('services.index.bridge_generator') }}</x-button>
                    <x-button :href="route('gallery')" variant="ghost" icon="arrow-right">{{ __('services.index.bridge_gallery') }}</x-button>
                </div>
            </div>
            <div class="services-bridge__art" data-reveal="tri">
                <img src="{{ asset('generated/gallery/pehouet-2.svg') }}" alt="" width="800" height="800" loading="lazy" decoding="async">
                <img src="{{ asset('generated/gallery/vitrail-1.svg') }}" alt="" width="800" height="800" loading="lazy" decoding="async">
                <img src="{{ asset('generated/gallery/eclats-3.svg') }}" alt="" width="800" height="800" loading="lazy" decoding="async">
            </div>
        </div>
    </section>

    <x-cta-band :title="__('services.index.cta_title')" :text="__('services.index.cta_text')" :href="route('contact')"
                :button="__('services.index.cta_button')" :secondary-href="route('about')" :secondary-button="__('services.index.cta_secondary')" />
@endsection
