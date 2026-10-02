{{-- Error page body inside the site layout (404, 419, 429). Sections: code, title, text. --}}
@extends('layouts.app')

@section('body_class', 'page-error')

@push('styles')
    <link rel="stylesheet" href="{{ ap_asset('css/pages/errors.css') }}">
@endpush

@section('content')
    <section class="error-page">
        <x-starfield :count="50" :seed="7" />
        <div class="container error-page__inner">
            @include('errors.broken')
            <div class="error-page__text">
                <p class="error-page__code">@yield('code')</p>
                <h1 class="error-page__title">@yield('heading')</h1>
                <p class="error-page__lead">@yield('text')</p>
                <div class="cluster error-page__actions">
                    <x-button :href="route('home')" icon="arrow-right">{{ __('errors.back_home') }}</x-button>
                    <x-button :href="route('services.index')" variant="ghost">{{ __('errors.services') }}</x-button>
                    <x-button :href="route('contact')" variant="link">{{ __('errors.contact') }}</x-button>
                </div>
            </div>
        </div>
    </section>
@endsection
