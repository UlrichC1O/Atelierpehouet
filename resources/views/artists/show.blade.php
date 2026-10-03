{{--
    One artist page — the gallery-grade reference template (docs/ARTISTS.md §5.1).
    View data (App\Http\Controllers\ArtistController@show): $artist (full array of ArtistDirectory::find()),
    $preview (bool: unpublished, admin only), $prev / $next (?summary), $others (list<summary>, ≤ 3).
    Sections: hero · facts band · biography · works (#oeuvres) · exhibitions (#expositions) · more artists · CTA.
--}}
@extends('layouts.app')

@php
    $preview = (bool) ($preview ?? false);
    $prev = $prev ?? null;
    $next = $next ?? null;
    $others = array_values($others ?? []);
    $locale = app()->getLocale();

    $works = array_values($artist['artworks'] ?? []);
    $groups = $artist['exhibitions'] ?? [];
    $now = array_values(array_merge($groups['current'] ?? [], $groups['upcoming'] ?? []));
    $cv = $artist['cv'] ?? [];
    $hasExhibitions = $now !== [] || $cv !== [];
    $links = array_values($artist['links'] ?? []);
    $countArtworks = (int) ($artist['counts']['artworks'] ?? count($works));
    $countExhibitions = (int) ($artist['counts']['exhibitions'] ?? 0);
    $pad = fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ratio = fn ($media): float => round($media->width / max(1, $media->height), 4);

    // Long names get a smaller display size (measured on the longest unbreakable segment and on the whole name).
    $nameSegments = preg_split('/[\s\-‐–]+/u', trim($artist['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [$artist['name']];
    $nameLongest = max(array_map('mb_strlen', $nameSegments));
    $nameLength = mb_strlen($artist['name']);
    $nameSize = $nameLongest <= 9 && $nameLength <= 22 ? 'm' : ($nameLongest <= 12 && $nameLength <= 40 ? 'l' : 'xl');
    $statementLong = mb_strlen((string) ($artist['statement'] ?? '')) > 170;

    // Hero visual: the portrait, else the key work (the first photographed work = the summary's cover), else the monogram.
    $photographed = array_values(array_filter($works, fn (array $work): bool => ! empty($work['media'])));
    $heroPortrait = $artist['portrait'] ?? null;
    $heroWork = $heroPortrait ? null : ($photographed[0] ?? null);
    $heroWorkMedia = $heroPortrait ? null : ($heroWork['media'] ?? ($artist['cover'] ?? null));
    $heroVariant = $heroPortrait ? 'portrait' : ($heroWorkMedia ? 'work' : 'monogram');
    // Biography aside: the key work, or the next photographed work when the hero already shows the key work.
    $asideWork = $heroPortrait ? ($photographed[0] ?? null) : ($photographed[1] ?? null);
    $shareImage = $heroPortrait ?? ($artist['cover'] ?? null);

    $availabilityCounts = collect($works)->countBy('availability')->all();
    $showFilters = count($works) >= 4 && ! empty($artist['availability_filters']);

    // JSON-LD: the artist (Person) + the current and upcoming exhibitions (ExhibitionEvent).
    $personId = $artist['url'].'#artist';
    $filled = fn (array $data): array => array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    $jsonld = [
        '@context' => 'https://schema.org',
        '@graph' => array_merge([
            $filled([
                '@type' => 'Person',
                '@id' => $personId,
                'name' => $artist['name'],
                'url' => $artist['url'],
                'image' => $shareImage?->url(1600),
                'jobTitle' => $artist['discipline'] ?? null,
                'description' => ($artist['statement'] ?? null) ?: $artist['meta_description'],
                'sameAs' => array_map(fn (array $link): string => $link['url'], $links),
                'homeLocation' => ! empty($artist['location']) ? ['@type' => 'Place', 'name' => $artist['location']] : null,
            ]),
        ], array_map(fn (array $expo): array => $filled([
            '@type' => 'ExhibitionEvent',
            'name' => $expo['title'],
            'startDate' => ($expo['starts_on'] ?? null) ?: (string) $expo['year'],
            'endDate' => $expo['ends_on'] ?? null,
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => (! empty($expo['venue']) || ! empty($expo['city']))
                ? $filled(['@type' => 'Place', 'name' => ($expo['venue'] ?? null) ?: $expo['city'], 'address' => $expo['city'] ?? null])
                : null,
            'description' => $expo['description'] ?? null,
            'image' => ($expo['media'] ?? null)?->url(1600),
            'url' => ($expo['url'] ?? null) ?: $artist['url'],
            'performer' => ['@id' => $personId],
        ]), $now)),
    ];
@endphp

@section('title', $artist['name'])
@section('meta_description', $artist['meta_description'])
@section('og_image', $shareImage?->url(1600) ?? '')
@section('body_class', 'page-artist')
@if (Route::has('admin.artists.edit'))
    @section('admin_edit_url', route('admin.artists.edit', $artist['id']))
    @section('admin_edit_label', __('artists.show.admin_edit'))
@endif

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/artists.css') }}">
@endpush

@push('head')
    <script type="application/ld+json">{!! json_encode($jsonld, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<article class="artist accent-{{ $artist['accent'] }}">
    @includeIf('partials.preview-banner', ['preview' => $preview, 'label' => __('artists.preview')])

    {{-- 1 · Hero: name, discipline, location, statement — portrait (or key work, or monogram) --}}
    <header class="artist-hero artist-hero--{{ $heroVariant }}">
        <div class="container artist-hero__inner">
            <div class="artist-hero__head">
                <x-breadcrumbs :items="[['label' => __('artists.nav'), 'url' => route('artists.index')], ['label' => $artist['name']]]" />
                <p class="eyebrow artist-hero__eyebrow">{{ ($artist['discipline'] ?? null) ?: __('artists.show.eyebrow') }}</p>
                <h1 class="artist-hero__name artist-hero__name--{{ $nameSize }}" data-split="words" data-split-anim="rise">{{ $artist['name'] }}</h1>
                @if (! empty($artist['location']))
                    <p class="artist-hero__location" data-reveal="fade-up" data-reveal-delay="160"><x-icon name="map-pin" /><span class="visually-hidden">{{ __('artists.a11y.location') }} </span>{{ $artist['location'] }}</p>
                @endif
            </div>

            <div class="artist-hero__visual">
                @php($frameTag = $heroWork ? 'figure' : 'div')
                <{{ $frameTag }} class="artist-frame artist-frame--{{ $heroVariant }}">
                    <span class="artist-frame__blocks" aria-hidden="true">
                        <span style="--i: 0"></span><span style="--i: 1"></span><span style="--i: 2"></span><span style="--i: 3"></span>
                    </span>
                    <div class="artist-portrait">
                        @if ($heroPortrait)
                            @include('artists.partials.image', ['media' => $heroPortrait, 'alt' => $heroPortrait->alt($locale) ?: __('artists.show.portrait_alt', ['name' => $artist['name']]), 'sizes' => '(min-width: 80em) 28rem, (min-width: 64em) 34vw, (min-width: 40em) 28rem, 86vw', 'width' => 960, 'lazy' => false, 'class' => 'artist-portrait__img', 'cover' => true, 'priority' => true])
                        @elseif ($heroWorkMedia)
                            <span class="artist-portrait__mat">
                                <span class="artist-portrait__work" style="aspect-ratio: {{ $heroWorkMedia->ratio() }}; --ratio: {{ $ratio($heroWorkMedia) }}">
                                    @include('artists.partials.image', ['media' => $heroWorkMedia, 'alt' => $heroWork['alt'] ?? $artist['name'], 'sizes' => '(min-width: 80em) 24rem, (min-width: 64em) 30vw, (min-width: 40em) 24rem, 74vw', 'width' => 960, 'lazy' => false, 'class' => 'artist-portrait__img', 'cover' => false, 'priority' => true])
                                </span>
                            </span>
                        @else
                            @include('artists.partials.monogram', ['artist' => $artist])
                        @endif
                        <span class="artist-portrait__seam" aria-hidden="true"></span>
                    </div>
                    <span class="artist-frame__tri" aria-hidden="true"></span>
                    @if ($heroWork)
                        <figcaption class="artist-frame__caption">
                            <span class="artist-frame__label">{{ __('artists.show.key_work') }}</span>
                            <span><cite>{{ $heroWork['title'] }}</cite>@if (! empty($heroWork['year'])), {{ $heroWork['year'] }}@endif</span>
                        </figcaption>
                    @endif
                </{{ $frameTag }}>
            </div>

            <div class="artist-hero__body" data-reveal="fade-up" data-reveal-delay="240">
                @if (! empty($artist['statement']))
                    <blockquote class="artist-hero__statement{{ $statementLong ? ' artist-hero__statement--long' : '' }}">
                        <p>{{ $artist['statement'] }}</p>
                    </blockquote>
                @endif
                <div class="cluster artist-hero__actions">
                    @if ($works)
                        <x-button href="#oeuvres" icon="arrow-down">{{ __('artists.show.actions.works') }}</x-button>
                    @endif
                    @if ($hasExhibitions)
                        <x-button href="#expositions" variant="ghost">{{ __('artists.show.actions.exhibitions') }}</x-button>
                    @endif
                    <x-button :href="route('contact')" variant="link" icon="arrow-right">{{ __('artists.show.actions.contact') }}</x-button>
                </div>
                @if (! empty($artist['example']))
                    <p class="artist-hero__example"><x-icon name="sparkle" />{{ __('artists.show.example') }}</p>
                @endif
            </div>
        </div>
    </header>

    {{-- 2 · Facts band: a Mondrian row of cells separated by seams; cells without a value are omitted --}}
    @if ($countArtworks > 0 || $countExhibitions > 0 || ! empty($artist['discipline']) || ! empty($artist['location']) || $links)
        <section class="artist-facts" aria-label="{{ __('artists.show.facts.label') }}">
            <div class="container">
                <dl class="artist-facts__grid" data-reveal="fade-up">
                    @if ($countArtworks > 0)
                        <div class="artist-facts__cell artist-facts__cell--count">
                            <dt class="artist-facts__label">{{ __('artists.show.facts.artworks') }}</dt>
                            <dd class="artist-facts__number"><span class="gradient-text" aria-hidden="true">{{ $pad($countArtworks) }}</span><span class="visually-hidden">{{ $countArtworks }}</span></dd>
                        </div>
                    @endif
                    @if ($countExhibitions > 0)
                        <div class="artist-facts__cell artist-facts__cell--count">
                            <dt class="artist-facts__label">{{ __('artists.show.facts.exhibitions') }}</dt>
                            <dd class="artist-facts__number"><span class="gradient-text" aria-hidden="true">{{ $pad($countExhibitions) }}</span><span class="visually-hidden">{{ $countExhibitions }}</span></dd>
                        </div>
                    @endif
                    @if (! empty($artist['discipline']))
                        <div class="artist-facts__cell">
                            <dt class="artist-facts__label">{{ __('artists.show.facts.discipline') }}</dt>
                            <dd class="artist-facts__value">{{ $artist['discipline'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($artist['location']))
                        <div class="artist-facts__cell">
                            <dt class="artist-facts__label">{{ __('artists.show.facts.location') }}</dt>
                            <dd class="artist-facts__value">{{ $artist['location'] }}</dd>
                        </div>
                    @endif
                    @if ($links)
                        <div class="artist-facts__cell artist-facts__cell--links">
                            <dt class="artist-facts__label">{{ __('artists.show.facts.links') }}</dt>
                            <dd class="artist-facts__value">
                                <ul class="artist-links" role="list">
                                    @foreach ($links as $link)
                                        <li><a class="artist-link" href="{{ $link['url'] }}" target="_blank" rel="noopener"><x-icon :name="($link['icon'] ?? 'globe') === 'instagram' ? 'instagram' : 'globe'" /><span class="artist-link__label">{{ $link['label'] }}</span><x-icon name="arrow-up-right" class="artist-link__arrow" /><span class="visually-hidden"> ({{ __('artists.a11y.new_tab') }})</span></a></li>
                                    @endforeach
                                </ul>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>
    @endif

    {{-- 3 · Biography + sticky aside (key work, fact sheet) --}}
    @if (! empty($artist['bio_html']))
        <section class="section artist-bio">
            <div class="container artist-bio__grid">
                <div class="artist-bio__main">
                    <x-section-heading :eyebrow="__('artists.show.bio.eyebrow')" :title="__('artists.show.bio.title')" :accent="$artist['accent']" />
                    <div class="prose artist-bio__prose" data-reveal="fade-up">{{ $artist['bio_html'] }}</div>
                </div>
                <aside class="artist-bio__aside" aria-label="{{ __('artists.show.bio.sheet') }}">
                    <div class="sticky-aside artist-bio__sticky">
                        @if ($asideWork)
                            <figure class="artist-keywork" data-reveal="fade-up">
                                <a class="artist-keywork__mat" href="{{ $asideWork['media']->url(1600) }}" data-lightbox="artist-keywork" data-caption="{{ $asideWork['caption'] }}">
                                    <span class="artist-keywork__frame" style="aspect-ratio: {{ $asideWork['media']->ratio() }}; --ratio: {{ $ratio($asideWork['media']) }}">
                                        @include('artists.partials.image', ['media' => $asideWork['media'], 'alt' => $asideWork['alt'], 'sizes' => '(min-width: 64em) 20rem, (min-width: 40em) 28rem, 86vw', 'width' => 960, 'lazy' => true, 'class' => 'artist-keywork__img', 'cover' => false, 'priority' => false])
                                    </span>
                                    <span class="visually-hidden">{{ __('artists.show.works.zoom') }}</span>
                                </a>
                                <figcaption class="artist-keywork__caption">
                                    @if ($heroPortrait)<span class="artist-keywork__label">{{ __('artists.show.key_work') }}</span>@endif
                                    <span><cite>{{ $asideWork['title'] }}</cite>@if (! empty($asideWork['year'])), {{ $asideWork['year'] }}@endif</span>
                                </figcaption>
                            </figure>
                        @endif
                        <div class="artist-sheet" data-reveal="fade-up" data-reveal-delay="120">
                            <p class="artist-sheet__title">{{ __('artists.show.bio.sheet') }}</p>
                            <dl class="artist-sheet__list">
                                @if (! empty($artist['discipline']))
                                    <div><dt>{{ __('artists.show.facts.discipline') }}</dt><dd>{{ $artist['discipline'] }}</dd></div>
                                @endif
                                @if (! empty($artist['location']))
                                    <div><dt>{{ __('artists.show.facts.location') }}</dt><dd>{{ $artist['location'] }}</dd></div>
                                @endif
                                @if ($countArtworks > 0)
                                    <div><dt>{{ __('artists.show.facts.artworks') }}</dt><dd>{{ $pad($countArtworks) }}</dd></div>
                                @endif
                                @if ($countExhibitions > 0)
                                    <div><dt>{{ __('artists.show.facts.exhibitions') }}</dt><dd>{{ $pad($countExhibitions) }}</dd></div>
                                @endif
                                @if ($links)
                                    <div>
                                        <dt>{{ __('artists.show.facts.links') }}</dt>
                                        <dd class="artist-sheet__links">
                                            @foreach ($links as $link)
                                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}<span class="visually-hidden"> ({{ __('artists.a11y.new_tab') }})</span></a>
                                            @endforeach
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                            <x-button :href="route('contact')" variant="outline" size="sm" icon="arrow-right" class="artist-sheet__cta">{{ __('artists.show.bio.inquire') }}</x-button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    @endif

    {{-- 4 · Selected works: wall mats at the natural ratio, availability filters, lightbox --}}
    @if ($works)
        <section class="section section--surface artist-works" id="oeuvres" data-filter-group data-filter-announce="{{ __('artists.show.works.announce') }}">
            <div class="container">
                <div class="artist-works__head">
                    <x-section-heading :eyebrow="__('artists.show.works.eyebrow')" :title="__('artists.show.works.title')" :accent="$artist['accent']" />
                    <p class="artist-works__count">{{ trans_choice('artists.counts.artworks', count($works)) }}</p>
                </div>
                @if ($showFilters)
                    <div class="filters artist-works__filters" role="group" aria-label="{{ __('artists.show.works.filter_label') }}">
                        <x-chip filter="all" :active="true">{{ __('artists.show.works.all') }} <span class="chip__count">{{ count($works) }}</span></x-chip>
                        @foreach ($artist['availability_filters'] as $availability)
                            <x-chip :filter="$availability"><span class="artwork__dot artwork__dot--{{ $availability }}" aria-hidden="true"></span>{{ __('artists.availability.'.$availability) }} <span class="chip__count">{{ $availabilityCounts[$availability] ?? 0 }}</span></x-chip>
                        @endforeach
                    </div>
                @endif
                <div class="artist-works__grid{{ count($works) < 3 ? ' artist-works__grid--few' : '' }}">
                    @foreach ($works as $artwork)
                        @include('artists.partials.artwork', ['artwork' => $artwork, 'artist' => $artist])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 5 · Exhibitions: current & upcoming as wide cards, then the full history (CV) --}}
    @if ($hasExhibitions)
        <section class="section artist-exhibitions" id="expositions">
            <div class="container">
                <x-section-heading :eyebrow="__('artists.show.exhibitions.eyebrow')" :title="__('artists.show.exhibitions.title')" :accent="$artist['accent']" />

                @if ($now)
                    <div class="artist-exhibitions__block">
                        <h3 class="artist-exhibitions__label">{{ __('artists.show.exhibitions.now') }}</h3>
                        <div class="artist-exhibitions__features">
                            @foreach ($now as $exhibition)
                                @include('artists.partials.exhibition', ['exhibition' => $exhibition, 'variant' => 'feature'])
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($cv)
                    <div class="artist-exhibitions__block artist-cv" data-inview>
                        <h3 class="artist-exhibitions__label">{{ __('artists.show.exhibitions.cv') }}</h3>
                        <div class="artist-cv__years">
                            @foreach ($cv as $year => $items)
                                <div class="artist-cv__year">
                                    <h4 class="artist-cv__numeral">{{ $year }}</h4>
                                    <ul class="artist-cv__list" role="list">
                                        @foreach ($items as $exhibition)
                                            @include('artists.partials.exhibition', ['exhibition' => $exhibition, 'variant' => 'cv'])
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- 6 · More artists: previous / next + up to 3 cards --}}
    @if ($others || $prev || $next)
        <section class="section section--surface artist-more">
            <div class="container">
                <x-section-heading :eyebrow="__('artists.show.more.eyebrow')" :title="__('artists.show.more.title')" :accent="$artist['accent']" />
                @if ($others)
                    <div class="artists-grid artist-more__grid" data-reveal-stagger="90">
                        @foreach ($others as $other)
                            @include('artists.partials.card', ['artist' => $other, 'index' => $loop->index, 'level' => 3, 'feature' => false])
                        @endforeach
                    </div>
                @endif
                <nav class="artist-nav" aria-label="{{ __('artists.show.more.nav_label') }}">
                    @if ($prev)
                        <a class="artist-nav__link artist-nav__link--prev accent-{{ $prev['accent'] }}" href="{{ $prev['url'] }}" rel="prev">
                            <span class="artist-nav__arrow" aria-hidden="true"><x-icon name="arrow-left" /></span>
                            <span class="artist-nav__label">{{ __('artists.show.more.prev') }}</span>
                            <span class="artist-nav__name">{{ $prev['name'] }}</span>
                        </a>
                    @endif
                    <a class="artist-nav__all" href="{{ route('artists.index') }}"><x-icon name="grid" /><span>{{ __('artists.show.more.all') }}</span></a>
                    @if ($next)
                        <a class="artist-nav__link artist-nav__link--next accent-{{ $next['accent'] }}" href="{{ $next['url'] }}" rel="next">
                            <span class="artist-nav__arrow" aria-hidden="true"><x-icon name="arrow-right" /></span>
                            <span class="artist-nav__label">{{ __('artists.show.more.next') }}</span>
                            <span class="artist-nav__name">{{ $next['name'] }}</span>
                        </a>
                    @endif
                </nav>
            </div>
        </section>
    @endif

    {{-- 7 · Closing call to action --}}
    <x-cta-band :title="__('artists.show.cta.title')" :text="__('artists.show.cta.text')"
                :href="route('contact')" :button="__('artists.show.cta.button')"
                :secondary-href="route('artists.index')" :secondary-button="__('artists.show.cta.secondary')" />
</article>
@endsection
