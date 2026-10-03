{{--
    HTTP 413 on an admin URL: the request was bigger than the server accepts (docs/CMS.md §13 C10),
    rendered by App\Cms\AdminErrors::tooLarge() from bootstrap/app.php. The browser's uploader
    resizes photos before sending them; this page catches a form posted without it.
    Vars: $size (?string, e.g. "4 Mo"), $back (string: the admin page the form came from).
--}}
@extends('admin.layouts.guest')

@section('title', __('admin.too_large.title'))

@section('content')
    <div class="adm-login adm-too-large">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __('admin.too_large.heading') }}</h1>
            <p class="adm-login__lead">{{ $size === null ? __('admin.too_large.lead_unknown') : __('admin.too_large.lead', ['size' => $size]) }}</p>
        </div>

        <a class="btn btn--lg adm-login__submit" href="{{ $back }}">
            <span class="btn__label">{{ __('admin.too_large.back') }}</span>
            <span class="btn__icon"><x-icon name="arrow-left" /></span>
        </a>
    </div>
@endsection
