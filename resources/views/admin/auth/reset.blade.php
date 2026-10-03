{{--
    New password from an e-mailed link (docs/CMS.md §13 D19) — Admin\PasswordController@reset, posts to @update.
    Vars: $token (from the URL), $email (from the link, editable), $linkFailed (bool: unknown or expired
    token ⇒ the error sits on "email" and a new link is offered).
--}}
@extends('admin.layouts.guest')

@php($minPassword = \App\Http\Controllers\Admin\PasswordController::MIN_PASSWORD)

@section('title', __('admin.password.reset_title'))

@section('content')
    <div class="adm-login">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __('admin.password.reset_title') }}</h1>
            <p class="adm-login__lead">{{ __('admin.password.reset_lead') }}</p>
        </div>

        <form class="adm-form adm-login__form" method="POST" action="{{ route('admin.password.update', ['token' => $token]) }}" data-adm-busy>
            @csrf
            <x-admin.field name="email" type="email" :label="__('admin.auth.email')" :value="$email" required
                           autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email" :autofocus="$email === ''" />
            <x-admin.field name="password" type="password" :label="__('admin.password.new_password')" required
                           :hint="__('admin.password.new_password_hint', ['min' => $minPassword])"
                           autocomplete="new-password" :minlength="$minPassword" :autofocus="$email !== ''" />
            <x-admin.field name="password_confirmation" type="password" :label="__('admin.password.confirm')" required
                           autocomplete="new-password" :minlength="$minPassword" />
            <button class="btn btn--lg adm-login__submit" type="submit">
                <span class="btn__label">{{ __('admin.password.save') }}</span>
                <span class="btn__icon"><x-icon name="check" /></span>
            </button>
        </form>

        @if ($linkFailed && \Illuminate\Support\Facades\Route::has('admin.password.request'))
            <a class="btn btn--ghost adm-login__submit" href="{{ route('admin.password.request') }}">
                <span class="btn__label">{{ __('admin.password.new_link') }}</span>
                <span class="btn__icon"><x-icon name="mail" /></span>
            </a>
        @endif

        <a class="adm-login__link" href="{{ route('admin.login') }}">
            <x-icon name="arrow-left" />
            <span>{{ __('admin.password.back') }}</span>
        </a>
    </div>
@endsection
