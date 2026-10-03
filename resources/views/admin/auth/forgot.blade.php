{{--
    "Mot de passe oublié ?" (docs/CMS.md §13 D19) — Admin\PasswordController@request, posts to @email.
    Vars: $mailable (bool: this site really sends e-mails), $minutes (lifetime of a link).
    Without a real mailer there is no form: the page explains to ask another administrator.
    The answer to the form never says whether the address has an account (session('status')).
--}}
@extends('admin.layouts.guest')

@section('title', __('admin.password.forgot_title'))

@section('content')
    <div class="adm-login">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __('admin.password.forgot_title') }}</h1>
            <p class="adm-login__lead">{{ __($mailable ? 'admin.password.forgot_lead' : 'admin.password.no_mail_lead') }}</p>
        </div>

        @if ($mailable)
            <form class="adm-form adm-login__form" method="POST" action="{{ route('admin.password.email') }}" data-adm-busy>
                @csrf
                <x-admin.field name="email" type="email" :label="__('admin.auth.email')" required
                               autocomplete="username" autocapitalize="none" spellcheck="false" inputmode="email" autofocus />
                <button class="btn btn--lg adm-login__submit" type="submit">
                    <span class="btn__label">{{ __('admin.password.send') }}</span>
                    <span class="btn__icon"><x-icon name="mail" /></span>
                </button>
            </form>
        @else
            <div class="adm-login__note" role="note">
                <span class="adm-login__note-icon" aria-hidden="true"><x-admin.icon name="info" /></span>
                <div class="adm-login__note-body">
                    <p>{{ __('admin.password.no_mail_help') }}</p>
                    <p>{{ __('admin.password.no_mail_alone') }}</p>
                </div>
            </div>
        @endif

        <a class="adm-login__link" href="{{ route('admin.login') }}">
            <x-icon name="arrow-left" />
            <span>{{ __('admin.password.back') }}</span>
        </a>
    </div>
@endsection
