{{--
    A free page created in the CMS (docs/CMS.md §7.4), rendered by CustomPageController@show.
    Vars: $page (array, Cms::page() / Cms::preview()), $title, $body (HtmlString: App\Cms\Markdown output —
    HTML stripped, unsafe links disabled, library photos only), $bodyLocale ('fr' when the English body
    is empty), $translated (bool), $meta (?string), $cover (?App\Cms\MediaItem), $updated (?['iso', 'label']),
    $preview (bool: an administrator looks at an unpublished page).
--}}
@extends('layouts.app')

@section('title', $title)
@section('meta_description', $meta ?? __('pages.meta'))
@section('body_class', 'page-custom')
@if ($cover)
    @section('og_image', $cover->url(960))
@endif
@section('admin_edit_url', route('admin.pages.edit', $page['id']))
@section('admin_edit_label', __('pages.edit'))

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/custom.css') }}">
@endpush

@section('content')
    @if ($preview)
        @if (view()->exists('partials.preview-banner'))
            @include('partials.preview-banner', ['preview' => true, 'label' => __('pages.preview')])
        @else
            <div class="custom-page__preview" role="status"><p class="custom-page__preview-text">{{ __('pages.preview') }}</p></div>
        @endif
    @endif

    <article class="custom-page">
        <x-page-hero :eyebrow="__('pages.eyebrow')" :title="$title" :breadcrumbs="[['label' => $title]]" accent="orange"
                     class="custom-page__hero">
            @if ($cover)
                <x-slot:aside class="custom-page__visual">
                    <figure class="custom-page__cover">
                        <span class="custom-page__frame">
                            <img class="custom-page__img" src="{{ $cover->url(960) }}" srcset="{{ $cover->srcset() }}"
                                 sizes="(min-width: 64em) 38vw, 90vw" width="{{ $cover->width }}" height="{{ $cover->height }}"
                                 alt="{{ $cover->alt() }}" style="object-position: {{ $cover->objectPosition() }}"
                                 decoding="async" fetchpriority="high">
                        </span>
                        <span class="custom-page__accent ap-anim-scope" aria-hidden="true"></span>
                        @if ($cover->caption())
                            <figcaption class="custom-page__caption">{{ $cover->caption() }}</figcaption>
                        @endif
                    </figure>
                </x-slot:aside>
            @endif
        </x-page-hero>

        <section class="section section--tight custom-page__section accent-yellow">
            <div class="container container--narrow custom-page__inner">
                <span class="custom-page__seam" aria-hidden="true"></span>

                @unless ($translated)
                    <p class="custom-page__notice">{{ __('pages.french_only') }}</p>
                @endunless

                @if (trim((string) $body) !== '')
                    <div class="prose custom-page__body" @if ($bodyLocale !== app()->getLocale()) lang="{{ $bodyLocale }}" @endif>{!! $body !!}</div>
                @else
                    <p class="custom-page__empty">{{ __('pages.empty') }}</p>
                @endif

                @if ($updated)
                    <p class="custom-page__updated">
                        <x-icon name="calendar" />
                        <span>{{ __('pages.updated', ['date' => $updated['label']]) }}</span>
                    </p>
                @endif
            </div>
        </section>
    </article>

    <x-cta-band :title="__('pages.cta.title')" :text="__('pages.cta.text')" :href="route('contact')" :button="__('pages.cta.button')"
                :secondary-href="route('services.index')" :secondary-button="__('pages.cta.secondary')" />
@endsection
