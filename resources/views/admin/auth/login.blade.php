{{--
    Admin sign-in (docs/CMS.md §7.1) — posts to admin.login.attempt (Admin\AuthController@login).
    Errors (wrong credentials, lockout) come back on "email"; database outages as session('error').
    Var: $rememberDays (?int) how long "Rester connecté" lasts (§13 D17).
--}}
@extends('admin.layouts.guest')

@php($rememberDays = $rememberDays ?? null)

@section('title', __('admin.auth.title'))

@section('content')
    <div class="adm-login">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __('admin.auth.heading') }}</h1>
            <p class="adm-login__lead">{{ __('admin.auth.lead') }}</p>
        </div>

        <form class="adm-form adm-login__form" method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <x-admin.field name="email" type="email" :label="__('admin.auth.email')" required
                           autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email" autofocus />
            <x-admin.field name="password" type="password" :label="__('admin.auth.password')" required
                           autocomplete="current-password" />
            <div class="adm-login__remember">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) @if ($rememberDays) aria-describedby="login-remember-hint" @endif>
                    <span>{{ __('admin.auth.remember') }}</span>
                </label>
                @if ($rememberDays)
                    <p class="adm-login__hint" id="login-remember-hint">{{ trans_choice('admin.auth.remember_hint', $rememberDays, ['days' => $rememberDays]) }}</p>
                @endif
            </div>
            <button class="btn btn--lg adm-login__submit" type="submit">
                <span class="btn__label">{{ __('admin.auth.submit') }}</span>
                <span class="btn__icon"><x-icon name="arrow-right" /></span>
            </button>
            {{-- Password reset (docs/CMS.md §13 D19), once its routes exist. --}}
            @if (\Illuminate\Support\Facades\Route::has('admin.password.request'))
                <a class="adm-login__forgot" href="{{ route('admin.password.request') }}">{{ __('admin.auth.forgot') }}</a>
            @endif
        </form>
    </div>
@endsection
