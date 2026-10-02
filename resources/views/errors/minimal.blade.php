{{--
    Self-contained error document for 500 / 503: no database, session, view composer or route
    is needed, so it renders even when the application is failing. Variables: $code (string).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('errors.'.$code.'.title') }} · {{ config('atelier.name', 'Ateliers Pehouet') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/01-tokens.css">
    <link rel="stylesheet" href="/css/02-base.css">
    <link rel="stylesheet" href="/css/03-layout.css">
    <link rel="stylesheet" href="/css/04-components.css">
    <link rel="stylesheet" href="/css/pages/errors.css">
</head>
<body class="page-error">
    <main class="error-page error-page--minimal">
        <div class="container error-page__inner">
            @include('errors.broken')
            <div class="error-page__text">
                <p class="error-page__code">{{ __('errors.'.$code.'.code') }}</p>
                <h1 class="error-page__title">{{ __('errors.'.$code.'.title') }}</h1>
                <p class="error-page__lead">{{ __('errors.'.$code.'.text') }}</p>
                <div class="cluster error-page__actions">
                    <a class="btn btn--primary" href="/"><span class="btn__label">{{ __('errors.back_home') }}</span></a>
                    <a class="btn btn--ghost" href="/contact"><span class="btn__label">{{ __('errors.contact') }}</span></a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
