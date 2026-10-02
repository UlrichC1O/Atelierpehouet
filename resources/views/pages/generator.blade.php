{{--
    "Atelier numérique": the visitor types a word, picks a style and the Python engine composes an artwork.
    Works without JS (GET form re-renders the preview); generator.js adds live updates, dice, copy and history.
--}}
@extends('layouts.app')

@section('title', __('generator.title'))
@section('meta_description', __('generator.meta'))
@section('body_class', 'page-generator')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/generator.css') }}">
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/generator.js') }}" defer></script>
@endpush

@php
    $words = (array) __('generator.words');
    $style = in_array(request('style'), $styles, true) ? request('style') : $defaultStyle;
    $seed = trim(mb_substr((string) request('seed', ''), 0, 60));
    $seed = $seed !== '' ? $seed : ($words[0] ?? 'Pehouet');
    $size = in_array((int) request('size'), $sizes, true) ? (int) request('size') : (in_array(800, $sizes, true) ? 800 : ($sizes[0] ?? 800));
    $animate = request('animate') === '1';
    $query = ['style' => $style, 'seed' => $seed, 'size' => $size, 'animate' => $animate ? 1 : 0];
    $src = $artUrl.'?'.http_build_query($query);
    $download = $artUrl.'?'.http_build_query($query + ['download' => 1]);
    $styleName = fn (string $key) => __('generator.styles.'.$key.'.name');
@endphp

@section('content')
    <x-page-hero :eyebrow="__('generator.eyebrow')" :title="__('generator.hero_title')" :lead="__('generator.lead')"
                 :breadcrumbs="[['label' => __('ui.nav.generator')]]" accent="yellow" compact />

    <section class="section section--flush-top generator" id="studio">
        <div class="container container--wide generator__layout">
            <form class="generator__form" method="get" action="{{ route('generator') }}#studio" data-generator-form
                  data-art-url="{{ $artUrl }}" data-words='@json(array_values($words))' aria-label="{{ __('generator.form.label') }}">
                <div class="field">
                    <label class="field__label" for="gen-seed">{{ __('generator.form.seed') }}</label>
                    <input class="field__input generator__seed" id="gen-seed" name="seed" type="text" maxlength="60" value="{{ $seed }}"
                           placeholder="{{ __('generator.form.seed_placeholder') }}" aria-describedby="gen-seed-hint" autocomplete="off" spellcheck="false">
                    <p class="field__hint" id="gen-seed-hint">{{ __('generator.form.seed_hint') }}</p>
                </div>

                <fieldset class="generator__styles">
                    <legend class="field__label">{{ __('generator.form.style') }}</legend>
                    <div class="generator__style-grid">
                        @foreach ($styles as $key)
                            <label class="style-card">
                                <input type="radio" name="style" value="{{ $key }}" @checked($key === $style)>
                                <span class="style-card__inner">
                                    <img class="style-card__img" src="{{ asset('generated/gallery/'.$key.'-1.svg') }}" alt="" width="96" height="96" loading="lazy" decoding="async">
                                    <span class="style-card__name">{{ $styleName($key) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="generator__row">
                    <div class="field">
                        <label class="field__label" for="gen-size">{{ __('generator.form.size') }}</label>
                        <select class="field__input" id="gen-size" name="size">
                            @foreach ($sizes as $option)
                                <option value="{{ $option }}" @selected($option === $size)>{{ __('generator.form.size_px', ['size' => $option]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <label class="checkbox generator__animate">
                        <input type="checkbox" name="animate" value="1" @checked($animate)>
                        <span>{{ __('generator.form.animate') }}<br><small class="muted">{{ __('generator.form.animate_hint') }}</small></span>
                    </label>
                </div>

                <div class="cluster generator__buttons">
                    <x-button type="submit" icon="sparkle" data-generator-submit>{{ __('generator.form.submit') }}</x-button>
                    <x-button variant="ghost" icon-before="dice" data-generator-random data-burst hidden>{{ __('generator.form.random') }}</x-button>
                </div>
            </form>

            <figure class="generator__preview" aria-label="{{ __('generator.preview.label') }}">
                <div class="generator__easel" data-generator-stage>
                    <img class="generator__img" src="{{ $src }}" width="800" height="800" data-generator-img
                         alt="{{ __('generator.preview.alt', ['seed' => $seed, 'style' => $styleName($style)]) }}"
                         data-alt-template="{{ __('generator.preview.alt', ['seed' => '__SEED__', 'style' => '__STYLE__']) }}">
                    <span class="generator__loading" data-generator-loading aria-hidden="true"><span>{{ __('generator.preview.loading') }}</span></span>
                </div>
                <p class="alert alert--error generator__error" data-generator-error role="alert" hidden>{{ __('generator.preview.error') }}</p>
                <figcaption class="cluster generator__actions">
                    <x-button :href="$download" variant="secondary" size="sm" icon-before="download" data-generator-download>{{ __('generator.preview.download') }}</x-button>
                    <x-button variant="ghost" size="sm" icon-before="external" :href="$src" data-generator-open external>{{ __('generator.preview.open') }}</x-button>
                    <button class="btn btn--ghost btn--sm" type="button" data-generator-copy data-label-copied="{{ __('generator.preview.copied') }}" hidden>
                        <span class="btn__icon btn__icon--before"><x-icon name="arrow-up-right" /></span><span class="btn__label">{{ __('generator.preview.copy') }}</span>
                    </button>
                    <span class="visually-hidden" aria-live="polite" data-generator-status></span>
                </figcaption>
            </figure>
        </div>

        <div class="container container--wide generator__history" data-generator-history-wrap hidden>
            <div class="generator__history-head">
                <h2 class="h4">{{ __('generator.history.title') }}</h2>
                <button class="btn btn--link" type="button" data-generator-clear>{{ __('generator.history.clear') }}</button>
            </div>
            <ul class="generator__history-list" role="list" data-generator-history data-empty="{{ __('generator.history.empty') }}"></ul>
        </div>
    </section>

    <section class="section section--surface generator-styles">
        <div class="container">
            <x-section-heading :title="__('generator.styles_title')" :lead="__('generator.styles_lead')" align="center" />
            <div class="generator-styles__grid" data-reveal-stagger="70">
                @foreach ($styles as $key)
                    <a class="generator-style" href="{{ route('generator', ['style' => $key, 'seed' => $seed]) }}#studio" data-reveal="fade-up">
                        <img src="{{ asset('generated/gallery/'.$key.'-2.svg') }}" alt="" width="800" height="800" loading="lazy" decoding="async">
                        <span class="generator-style__name">{{ $styleName($key) }}</span>
                        <span class="generator-style__desc">{{ __('generator.styles.'.$key.'.desc') }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section generator-how">
        <div class="container split">
            <div class="stack" data-reveal="fade-right">
                <p class="eyebrow">{{ __('generator.how_eyebrow') }}</p>
                <h2 class="h2">{{ __('generator.how_title') }}</h2>
                <p class="lead">{{ __('generator.how_text') }}</p>
                <ul class="generator-how__points" role="list">
                    @foreach ((array) __('generator.how_points') as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="generator-how__visual" data-reveal="rotate" aria-hidden="true">
                <pre class="generator-how__code"><code><span class="tok-k">from</span> art_engine <span class="tok-k">import</span> render

svg = render(<span class="tok-s">"{{ $style }}"</span>, <span class="tok-s">"{{ $seed }}"</span>)
<span class="tok-c"># → {{ __('generator.how_comment') }}</span></code></pre>
                <x-orbit size="m" class="generator-how__orbit" />
            </div>
        </div>
    </section>

    <x-cta-band :title="__('generator.cta_title')" :text="__('generator.cta_text')" :href="route('contact')" :button="__('generator.cta_button')"
                :secondary-href="route('gallery')" :secondary-button="__('generator.cta_secondary')" />
@endsection
