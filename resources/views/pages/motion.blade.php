{{-- "Mouvement": the live, numbered catalogue of every animation (resources/content/animations.json). --}}
@extends('layouts.app')

@section('title', __('motion.title'))
@section('meta_description', __('motion.meta'))
@section('body_class', 'page-motion')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/motion.css') }}">
    @foreach ($scenes as $scene)
        @if (is_file(public_path('css/scenes/'.$scene['scene'].'.css')))
            <link rel="stylesheet" href="{{ ap_asset('css/scenes/'.$scene['scene'].'.css') }}">
        @endif
    @endforeach
@endpush
@push('scripts')
    <script src="{{ ap_asset('js/motion.js') }}" defer></script>
@endpush

@php
    $tiles = array_values(array_filter($animations, fn ($a) => ($a['group'] ?? '') !== 'scene'));
    $byScene = collect($animations)->where('group', 'scene')->groupBy('scene');
    $groupLabels = (array) __('motion.groups');
    $num = fn ($n) => '#'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    $animStyle = function (array $a): string {
        $dur = preg_match('/^[\d.]+m?s$/', (string) $a['dur']) ? $a['dur'] : '2.4s';
        $iter = preg_match('/^(infinite|\d+)$/', (string) $a['iter']) ? $a['iter'] : 'infinite';
        $dir = in_array($a['dir'], ['normal', 'alternate', 'reverse', 'alternate-reverse'], true) ? $a['dir'] : 'normal';
        $ease = preg_match('/^[a-z\-]+(\([^;{}<>]*\))?$/i', (string) $a['ease']) || str_starts_with((string) $a['ease'], 'var(--') ? $a['ease'] : 'ease-in-out';
        $iter = $iter === '1' ? 'infinite' : $iter;

        return "animation: {$a['name']} {$dur} {$ease} 0s {$iter} {$dir} both; animation-duration: calc({$dur} / var(--mspeed, 1)); animation-delay: calc(var(--i, 0) * 0.12s);";
    };
@endphp

@section('content')
    <section class="motion-hero ap-anim-scope">
        <x-starfield :count="60" :seed="11" />
        <x-floating-shapes :count="10" :seed="9" variant="outline" />
        <div class="container motion-hero__inner">
            <x-breadcrumbs :items="[['label' => __('ui.nav.motion')]]" />
            <p class="eyebrow">{{ __('motion.eyebrow') }}</p>
            <h1 class="motion-hero__title" data-split="chars" data-split-anim="neon">{{ __('motion.hero_title') }}</h1>
            <p class="motion-hero__count"><span class="motion-hero__number" data-count-to="{{ $total }}">{{ $total }}</span> <span class="motion-hero__label">{{ __('motion.count_label') }}</span></p>
            <div class="motion-hero__manifesto">
                @foreach ((array) __('motion.manifesto') as $paragraph)
                    <p class="lead" data-reveal="fade-up">{{ $paragraph }}</p>
                @endforeach
            </div>
        </div>
        <x-kaleidoscope size="l" class="motion-hero__kaleido" />
    </section>

    @if ($total === 0)
        <section class="section"><div class="container"><p class="lead">{{ __('motion.empty') }}</p></div></section>
    @else
        <section class="section section--flush-top motion-catalogue" data-filter-group data-filter-announce="{{ __('motion.controls.shown') }}">
            <div class="motion-controls" role="region" aria-label="{{ __('motion.controls.label') }}">
                <div class="container container--wide motion-controls__inner">
                    <div class="filters" role="group" aria-label="{{ __('motion.controls.label') }}">
                        <x-chip filter="all" :active="true">{{ __('motion.controls.all') }} <span class="chip__count">{{ count($tiles) }}</span></x-chip>
                        @foreach ($groups as $group => $entries)
                            @continue($group === 'scene')
                            <x-chip :filter="$group">{{ $groupLabels[$group] ?? $group }} <span class="chip__count">{{ count($entries) }}</span></x-chip>
                        @endforeach
                    </div>
                    <div class="motion-controls__tools">
                        <label class="motion-search">
                            <span class="visually-hidden">{{ __('motion.controls.search') }}</span>
                            <x-icon name="filter" />
                            <input type="search" data-motion-search placeholder="{{ __('motion.controls.search_placeholder') }}" autocomplete="off">
                        </label>
                        <div class="motion-speed" role="group" aria-label="{{ __('motion.controls.speed') }}">
                            @foreach (['0.5' => '0.5×', '1' => '1×', '2' => '2×'] as $value => $label)
                                <button class="motion-speed__btn" type="button" data-motion-speed="{{ $value }}" aria-pressed="{{ $value === '1' ? 'true' : 'false' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <button class="btn btn--ghost btn--sm" type="button" data-motion-pause data-label-pause="{{ __('motion.controls.pause') }}" data-label-play="{{ __('motion.controls.play') }}" aria-pressed="false">
                            <span class="btn__icon btn__icon--before"><x-icon name="pause" /></span><span class="btn__label">{{ __('motion.controls.pause') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="container container--wide">
                <p class="motion-count" data-motion-count data-template="{{ __('motion.controls.shown') }}" aria-live="polite"></p>
                <p class="motion-none muted" data-motion-none hidden>{{ __('motion.controls.none') }}</p>
                <div class="motion-grid" data-motion-grid>
                    @foreach ($tiles as $a)
                        <article class="mtile mtile--{{ $a['demo'] }} ap-anim-scope" data-filter-item data-category="{{ $a['group'] }}"
                                 data-name="{{ mb_strtolower($a['name'].' '.$a['title']) }}" tabindex="0" role="button"
                                 aria-label="{{ __('motion.tile.replay', ['name' => $a['title']]) }}" data-motion-tile>
                            <div class="mtile__stage" aria-hidden="true">
                                @switch($a['demo'])
                                    @case('blocks')
                                        <div class="mdemo mdemo--blocks">@for ($i = 0; $i < 5; $i++)<span class="mdemo__el" style="--i: {{ $i }}; {{ $animStyle($a) }}"></span>@endfor</div>
                                        @break
                                    @case('letters')
                                        <div class="mdemo mdemo--letters">@foreach (str_split('TELIERS') as $i => $letter)<span class="mdemo__el" style="--i: {{ $i }}; {{ $animStyle($a) }}">{{ $letter }}</span>@endforeach</div>
                                        @break
                                    @case('text')
                                        <div class="mdemo mdemo--text"><span class="mdemo__el" style="{{ $animStyle($a) }}">Pehouet</span></div>
                                        @break
                                    @case('path')
                                        <svg class="mdemo mdemo--path" viewBox="-6 -6 112 102" focusable="false"><polygon class="mdemo__el" pathLength="1" points="50,2 98,88 2,88" style="{{ $animStyle($a) }}"/></svg>
                                        @break
                                    @case('dots')
                                        <div class="mdemo mdemo--dots">@for ($i = 0; $i < 3; $i++)<span class="mdemo__el" style="--i: {{ $i }}; {{ $animStyle($a) }}"></span>@endfor</div>
                                        @break
                                    @default
                                        <div class="mdemo mdemo--{{ $a['demo'] }}"><span class="mdemo__el" style="{{ $animStyle($a) }}"></span></div>
                                @endswitch
                            </div>
                            <div class="mtile__meta">
                                <span class="mtile__num">{{ $num($a['n']) }}</span>
                                <h3 class="mtile__title">{{ $a['title'] }}</h3>
                                <code class="mtile__name">{{ $a['name'] }}</code>
                                <span class="mtile__group">{{ $groupLabels[$a['group']] ?? $a['group'] }} · {{ $a['file'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section section--surface motion-scenes">
            <div class="container container--wide">
                <x-section-heading :eyebrow="__('motion.scenes.eyebrow')" :title="__('motion.scenes.title')" :lead="__('motion.scenes.lead', ['count' => $scenes->count()])" accent="red" />
                <div class="motion-scenes__grid">
                    @foreach ($scenes as $scene)
                        <article class="motion-scene accent-{{ $scene['accent'] }}" data-reveal="fade-up">
                            <div class="motion-scene__stage scene-stage accent-{{ $scene['accent'] }}">
                                @includeIf('services.scenes.'.$scene['scene'], ['service' => $scene])
                            </div>
                            <div class="motion-scene__body">
                                <h3 class="motion-scene__title"><span>{{ $scene['number'] }}</span> {{ $scene['title'] }}</h3>
                                <p class="motion-scene__label">{{ __('motion.scenes.keyframes') }}</p>
                                <ul class="motion-scene__list" role="list">
                                    @foreach ($byScene->get($scene['slug'], collect()) as $a)
                                        <li><span class="mtile__num">{{ $num($a['n']) }}</span> {{ $a['title'] }}</li>
                                    @endforeach
                                </ul>
                                <a class="motion-scene__link" href="{{ $scene['url'] }}">{{ __('motion.scenes.open') }} <x-icon name="arrow-right" /></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section motion-a11y">
        <div class="container container--narrow motion-a11y__inner" data-reveal="fade-up">
            <h2 class="h3">{{ __('motion.a11y.title') }}</h2>
            <p>{{ __('motion.a11y.text') }}</p>
            @include('partials.motion-toggle')
        </div>
    </section>

    <x-cta-band :title="__('motion.cta_title')" :text="__('motion.cta_text')" :href="route('contact')" :button="__('motion.cta_button')"
                :secondary-href="route('services.index')" :secondary-button="__('motion.cta_secondary')" />
@endsection
