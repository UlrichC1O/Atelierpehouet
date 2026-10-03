{{--
    Artists index (docs/ARTISTS.md §5.2) — every published artist as a card; a calm empty state when there is
    none (HTTP 200) or when no artist data can be read at all (HTTP 503 + Retry-After, set by the controller).
    View data (App\Http\Controllers\ArtistController@index): $artists (list<summary>, §4.1) · $available (bool)
--}}
@extends('layouts.app')

@php
    $artists = array_values($artists ?? []);
    $available = (bool) ($available ?? false);
    $artistCount = count($artists);
    // Hero collage: up to five artists that have a portrait or a photographed work.
    $tiles = array_slice(array_values(array_filter($artists, fn (array $item): bool => ($item['portrait'] ?? null) !== null || ($item['cover'] ?? null) !== null)), 0, 5);
@endphp

@section('title', __('artists.index.title'))
@section('meta_description', __('artists.index.meta'))
@section('body_class', 'page-artists')
@if (Route::has('admin.artists.index'))
    @section('admin_edit_url', route('admin.artists.index'))
    @section('admin_edit_label', __('artists.index.admin_edit'))
@endif

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/artists.css') }}">
@endpush

@section('content')
    <x-page-hero :eyebrow="__('artists.index.eyebrow')" :title="__('artists.index.hero_title')" :lead="__('artists.index.lead')"
                 :breadcrumbs="[['label' => __('artists.nav')]]" accent="orange">
        @if ($artistCount)
            <x-button href="#artistes" icon="arrow-down">{{ __('artists.index.cta_discover') }}</x-button>
        @endif
        <x-button :href="route('contact')" variant="ghost">{{ __('artists.index.cta_exhibit') }}</x-button>
        <x-slot:aside class="artists-hero-art">
            @if ($tiles)
                <div class="artists-tiles artists-tiles--{{ count($tiles) }}" aria-hidden="true">
                    @foreach ($tiles as $tile)
                        <span class="artists-tiles__tile accent-{{ $tile['accent'] }}" style="--i: {{ $loop->index }}">
                            @if ($tile['portrait'] ?? null)
                                @include('artists.partials.image', ['media' => $tile['portrait'], 'alt' => '', 'sizes' => '(min-width: 64em) 15rem, 44vw', 'width' => 480, 'lazy' => false, 'class' => 'artists-tiles__img', 'cover' => true, 'priority' => false])
                            @else
                                <span class="artists-tiles__mat">
                                    <span class="artists-tiles__work" style="aspect-ratio: {{ $tile['cover']->ratio() }}; --ratio: {{ round($tile['cover']->width / max(1, $tile['cover']->height), 4) }}">
                                        @include('artists.partials.image', ['media' => $tile['cover'], 'alt' => '', 'sizes' => '(min-width: 64em) 12rem, 36vw', 'width' => 480, 'lazy' => false, 'class' => 'artists-tiles__img', 'cover' => false, 'priority' => false])
                                    </span>
                                </span>
                            @endif
                        </span>
                    @endforeach
                    <span class="artists-tiles__tri"></span>
                </div>
            @else
                <x-mondrian variant="b" class="artists-hero-art__mondrian" />
            @endif
        </x-slot:aside>
    </x-page-hero>

    <section class="section artists-list" id="artistes">
        <div class="container">
            @if ($artistCount)
                <div class="artists-list__head">
                    <h2 class="artists-list__title" data-split="words" data-split-anim="rise">{{ __('artists.index.list_title') }}</h2>
                    <p class="artists-list__count" data-reveal="fade-up">{{ trans_choice('artists.index.count', $artistCount) }}</p>
                </div>
                <div class="artists-grid{{ $artistCount === 1 ? ' artists-grid--single' : '' }}" data-reveal-stagger="90">
                    @foreach ($artists as $artist)
                        @include('artists.partials.card', ['artist' => $artist, 'index' => $loop->index, 'level' => 3, 'feature' => $artistCount === 1])
                    @endforeach
                </div>
            @else
                @php($emptyKey = $available ? 'empty' : 'unavailable')
                <div class="artists-empty" data-reveal="fade-up">
                    <div class="artists-empty__wall" aria-hidden="true">
                        <span class="artists-empty__frame artists-empty__frame--a"><span></span></span>
                        <span class="artists-empty__frame artists-empty__frame--b"><span></span></span>
                        <span class="artists-empty__frame artists-empty__frame--c"><span></span></span>
                        <span class="artists-empty__frame artists-empty__frame--d"><span></span></span>
                        <span class="artists-empty__tri"></span>
                    </div>
                    <div class="artists-empty__body">
                        <p class="eyebrow">{{ __('artists.index.'.$emptyKey.'.eyebrow') }}</p>
                        <h2 class="artists-empty__title">{{ __('artists.index.'.$emptyKey.'.title') }}</h2>
                        <p class="artists-empty__text">{{ __('artists.index.'.$emptyKey.'.text') }}</p>
                        <x-button :href="route('contact')" icon="arrow-right">{{ __('artists.index.'.$emptyKey.'.button') }}</x-button>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <x-cta-band :title="__('artists.index.cta.title')" :text="__('artists.index.cta.text')" :href="route('contact')"
                :button="__('artists.index.cta.button')" :secondary-href="route('about')" :secondary-button="__('artists.index.cta.secondary')" />
@endsection
