{{-- One service page (all services share this template; the scene and the copy make each one unique). --}}
@extends('layouts.app')

@section('title', $service['title'])
@section('meta_description', $service['meta_description'])
@section('body_class', 'page-service')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/services.css') }}">
    @if (is_file(public_path('css/scenes/'.$service['scene'].'.css')))
        <link rel="stylesheet" href="{{ ap_asset('css/scenes/'.$service['scene'].'.css') }}">
    @endif
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/services.js') }}" defer></script>
@endpush

@php
    $total = app(App\Support\ServiceCatalog::class)->count();
    $styleName = __('generator.styles.'.$service['art_style'].'.name');
    $jsonld = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Service',
                'name' => $service['title'],
                'description' => $service['meta_description'],
                'serviceType' => $service['category_label'],
                'url' => $service['url'],
                'inLanguage' => app()->getLocale(),
                'provider' => ['@id' => url('/').'#organization'],
                'areaServed' => ['@type' => 'Place', 'name' => config('atelier.contact.address') ?: config('atelier.name')],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($service['faq'])->map(fn ($item) => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ])->values()->all(),
            ],
        ],
    ];
@endphp

@push('head')
    <script type="application/ld+json">{!! json_encode($jsonld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<article class="service accent-{{ $service['accent'] }}" data-service-page
         data-prev="{{ $prev['url'] ?? '' }}" data-next="{{ $next['url'] ?? '' }}">

    {{-- Hero: title + scene stage --}}
    <header class="service-hero">
        <x-floating-shapes :count="9" :seed="$service['order']" variant="outline" />
        <div class="container container--wide service-hero__inner">
            <div class="service-hero__text">
                <x-breadcrumbs :items="[['label' => __('ui.nav.services'), 'url' => route('services.index')], ['label' => $service['title']]]" />
                <p class="eyebrow">{{ $service['category_label'] }} · {{ __('services.show.number', ['number' => $service['number'], 'total' => str_pad((string) $total, 2, '0', STR_PAD_LEFT)]) }}</p>
                <h1 class="service-hero__title" data-split="words" data-split-anim="rise">{{ $service['title'] }}</h1>
                <p class="service-hero__tagline" data-reveal="fade-up" data-reveal-delay="200">{{ $service['tagline'] }}</p>
                <div class="cluster service-hero__actions" data-reveal="fade-up" data-reveal-delay="320">
                    <x-button :href="route('contact', ['service' => $service['slug']])" icon="arrow-right" magnetic>{{ __('services.show.quote') }}</x-button>
                    <x-button href="#inspirations" variant="ghost" icon="arrow-down">{{ __('services.show.see_inspirations') }}</x-button>
                </div>
            </div>
            <figure class="service-hero__figure" data-reveal="zoom-in" data-reveal-delay="120">
                <div class="scene-stage accent-{{ $service['accent'] }}" data-stage>
                    @includeIf('services.scenes.'.$service['scene'], ['service' => $service])
                    @if (! view()->exists('services.scenes.'.$service['scene']))
                        <div class="scene scene--fallback ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] }}"><x-logo-mark animated idle decorative /></div>
                    @endif
                    <span class="scene-stage__corner scene-stage__corner--tl" aria-hidden="true"></span>
                    <span class="scene-stage__corner scene-stage__corner--br" aria-hidden="true"></span>
                </div>
                <figcaption class="service-hero__caption"><span class="service-hero__num" aria-hidden="true">{{ $service['number'] }}</span> {{ $service['scene_alt'] }}</figcaption>
            </figure>
        </div>
    </header>

    {{-- Intro + sticky aside --}}
    <section class="section service-intro">
        <div class="container service-intro__grid">
            <div class="service-intro__text prose">
                <p class="service-intro__lead" data-reveal="fade-up">{{ $service['intro'] }}</p>
                @foreach ($service['body'] as $paragraph)
                    <p data-reveal="fade-up">{{ $paragraph }}</p>
                @endforeach
            </div>
            <aside class="service-intro__aside">
                <div class="sticky-aside stack">
                    <div class="service-card-facts" data-reveal="fade-left">
                        <p class="service-card-facts__title">{{ __('services.show.facts_title') }}</p>
                        <dl>
                            <div><dt>{{ __('services.show.facts_category') }}</dt><dd>{{ $service['category_label'] }}</dd></div>
                            <div><dt>{{ __('services.show.facts_number') }}</dt><dd>{{ $service['number'] }} / {{ str_pad((string) $total, 2, '0', STR_PAD_LEFT) }}</dd></div>
                            <div><dt>{{ __('services.show.facts_price') }}</dt><dd>{{ __('services.show.facts_price_value') }}</dd></div>
                        </dl>
                        <p class="service-card-facts__note"><x-icon name="check" /> {{ __('services.show.facts_quote') }}</p>
                    </div>
                    <div class="service-ideal" data-reveal="fade-left" data-reveal-delay="120">
                        <p class="service-ideal__title">{{ __('services.show.ideal_for') }}</p>
                        <ul class="service-ideal__list" role="list">
                            @foreach ($service['ideal_for'] as $audience)
                                <li>{{ $audience }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    {{-- Features --}}
    <section class="section section--surface service-features">
        <div class="container">
            <x-section-heading :eyebrow="__('services.show.features_eyebrow')" :title="__('services.show.features_title')" :accent="$service['accent']" />
            <div class="service-features__grid" data-reveal-stagger="80">
                @foreach ($service['features'] as $feature)
                    <x-feature :icon="$loop->first ? $service['icon'] : ['sparkle', 'palette', 'triangle', 'heart', 'star', 'users'][$loop->index % 6]"
                               :title="$feature['title']" :text="$feature['text']" :index="$loop->index" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Process --}}
    <section class="section service-process">
        <div class="container">
            <x-section-heading :eyebrow="__('services.show.process_eyebrow')" :title="__('services.show.process_title')" :accent="$service['accent']" />
            <ol class="steps" role="list" data-inview>
                @foreach ($service['process'] as $step)
                    <x-step :number="str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT)" :title="$step['title']" :text="$step['text']" />
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Inspirations (generated by the Python engine) --}}
    <section class="section section--surface service-inspirations" id="inspirations">
        <div class="container">
            <x-section-heading :eyebrow="__('services.show.inspirations_eyebrow')" :title="__('services.show.inspirations_title')"
                               :lead="__('services.show.inspirations_lead', ['style' => $styleName])" :accent="$service['accent']" />
            <div class="service-inspirations__grid" data-reveal-stagger="120">
                @foreach ($inspirations as $art)
                    <x-art-frame :src="$art['src']" :alt="$art['title']" :caption="$art['title']" :href="$art['src']" group="inspirations"
                                 data-reveal="flip-y" :width="$art['width'] ?? 800" :height="$art['height'] ?? 800" />
                @endforeach
            </div>
            <p class="service-inspirations__more" data-reveal="fade-up">
                <x-button :href="route('generator', ['style' => $service['art_style'], 'seed' => $service['title']])" variant="outline" icon="sparkle">{{ __('services.show.inspirations_generator') }}</x-button>
            </p>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="section service-faq">
        <div class="container container--narrow">
            <x-section-heading :eyebrow="__('services.show.faq_eyebrow')" :title="__('services.show.faq_title')" :accent="$service['accent']" />
            <div class="service-faq__list" data-reveal="fade-up">
                @foreach ($service['faq'] as $item)
                    <x-accordion-item :question="$item['q']" :open="$loop->first">{{ $item['a'] }}</x-accordion-item>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Related + prev/next --}}
    <section class="section section--surface service-related">
        <div class="container container--wide">
            <x-section-heading :eyebrow="__('services.show.related_eyebrow')" :title="__('services.show.related_title')" :accent="$service['accent']" />
            <div class="grid grid--3">
                @foreach ($related as $item)
                    <x-service-card :service="$item" :index="$loop->index" variant="compact" />
                @endforeach
            </div>

            <nav class="service-nav" aria-label="{{ __('services.show.nav_label') }}">
                @if ($prev)
                    <a class="service-nav__link service-nav__link--prev accent-{{ $prev['accent'] }}" href="{{ $prev['url'] }}" rel="prev">
                        <span class="service-nav__arrow" aria-hidden="true"><x-icon name="arrow-left" /></span>
                        <span class="service-nav__label">{{ __('services.show.prev') }}</span>
                        <span class="service-nav__title"><span class="service-nav__num">{{ $prev['number'] }}</span> {{ $prev['title'] }}</span>
                    </a>
                @endif
                <a class="service-nav__all" href="{{ route('services.index') }}">
                    <x-icon name="grid" /> <span>{{ __('services.show.all') }}</span>
                </a>
                @if ($next)
                    <a class="service-nav__link service-nav__link--next accent-{{ $next['accent'] }}" href="{{ $next['url'] }}" rel="next">
                        <span class="service-nav__arrow" aria-hidden="true"><x-icon name="arrow-right" /></span>
                        <span class="service-nav__label">{{ __('services.show.next') }}</span>
                        <span class="service-nav__title"><span class="service-nav__num">{{ $next['number'] }}</span> {{ $next['title'] }}</span>
                    </a>
                @endif
            </nav>
            <p class="service-nav__hint muted">{{ __('services.show.keyboard_hint') }}</p>
        </div>
    </section>

    <x-cta-band :title="__('services.show.cta_title')" :text="__('services.show.cta_text')"
                :href="route('contact', ['service' => $service['slug']])" :button="__('services.show.cta_button')"
                :secondary-href="route('services.index')" :secondary-button="__('services.show.cta_secondary')" />
</article>
@endsection
