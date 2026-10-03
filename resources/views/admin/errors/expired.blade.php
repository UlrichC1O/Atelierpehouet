{{--
    HTTP 419 on an admin URL (docs/CMS.md §13 D20), rendered by Admin\AuthController@expired from
    bootstrap/app.php. Vars: $signedIn (bool: the session still holds a user — the page was only out
    of date), $back (?string: the admin page the form came from, same site only).
    On submit admin.js copied the changed forms to sessionStorage: they come back into the form once
    it shows again (after signing in, a guest is sent back to $back).
--}}
@extends('admin.layouts.guest')

@section('title', __('admin.expired.title'))

@section('content')
    <div class="adm-login adm-expired">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __($signedIn ? 'admin.expired.reload_heading' : 'admin.expired.heading') }}</h1>
            <p class="adm-login__lead">{{ __($signedIn ? 'admin.expired.reload_lead' : 'admin.expired.lead') }}</p>
        </div>

        @if ($signedIn)
            <a class="btn btn--lg adm-login__submit" href="{{ $back ?? route('admin.dashboard') }}">
                <span class="btn__label">{{ __($back !== null ? 'admin.expired.back' : 'admin.expired.dashboard') }}</span>
                <span class="btn__icon"><x-icon name="arrow-right" /></span>
            </a>
        @else
            <div class="adm-login__note" role="note">
                <span class="adm-login__note-icon" aria-hidden="true"><x-admin.icon name="history" /></span>
                <div class="adm-login__note-body">
                    <p>{{ __('admin.expired.drafts') }}</p>
                </div>
            </div>

            <a class="btn btn--lg adm-login__submit" href="{{ route('admin.login') }}">
                <span class="btn__label">{{ __('admin.expired.login') }}</span>
                <span class="btn__icon"><x-icon name="arrow-right" /></span>
            </a>
        @endif
    </div>
@endsection
