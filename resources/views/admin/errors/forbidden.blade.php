{{--
    HTTP 403 on an admin URL: a signed-in account without the admin right (Gate "admin",
    docs/CMS.md §13 D18), rendered by App\Cms\AdminErrors::forbidden() from bootstrap/app.php.
    Var: $signedIn (bool: offer to sign out, the only admin action such an account may take).
--}}
@extends('admin.layouts.guest')

@section('title', __('admin.forbidden.title'))

@section('content')
    <div class="adm-login adm-forbidden">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __('admin.forbidden.heading') }}</h1>
            <p class="adm-login__lead">{{ __('admin.forbidden.lead') }}</p>
        </div>

        @if ($signedIn)
            <form class="adm-stack" method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn btn--lg adm-login__submit" type="submit">
                    <span class="btn__label">{{ __('admin.forbidden.logout') }}</span>
                    <span class="btn__icon"><x-admin.icon name="logout" /></span>
                </button>
            </form>
        @endif

        <a class="adm-login__link" href="{{ route('home') }}">
            <x-admin.icon name="home" />
            <span>{{ __('admin.forbidden.site') }}</span>
        </a>
    </div>
@endsection
