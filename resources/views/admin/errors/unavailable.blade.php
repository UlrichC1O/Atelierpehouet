{{--
    HTTP 503 on an admin URL that met a database error (docs/CMS.md §13 A3), rendered by
    App\Cms\AdminErrors::unavailable() from bootstrap/app.php — without any database query.
    Vars: $outage (bool: the database is unreachable, e.g. a paused Supabase project — else it
    answered with an error, e.g. pending migrations), $retryUrl (string), $signedIn (bool).
--}}
@extends('admin.layouts.guest')

@section('title', __('admin.unavailable.title'))

@section('content')
    <div class="adm-login adm-unavailable">
        <div class="adm-login__head">
            <p class="adm-login__eyebrow">
                <span class="adm-login__tri" aria-hidden="true"></span>
                {{ __('admin.auth.eyebrow') }}
            </p>
            <h1 class="adm-login__title">{{ __($outage ? 'admin.unavailable.heading' : 'admin.unavailable.error_heading') }}</h1>
            <p class="adm-login__lead">{{ __($outage ? 'admin.unavailable.lead' : 'admin.unavailable.error_lead') }}</p>
        </div>

        @if ($outage)
            <div class="adm-login__note" role="note">
                <span class="adm-login__note-icon" aria-hidden="true"><x-admin.icon name="database" /></span>
                <div class="adm-login__note-body prose">
                    <p><strong>{{ __('admin.unavailable.wake_title') }}</strong></p>
                    <p>{{ __('admin.unavailable.wake_intro') }}</p>
                    <ol>
                        @foreach ((array) __('admin.unavailable.wake_steps') as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        @endif

        <div class="adm-stack">
            <a class="btn btn--lg adm-login__submit" href="{{ $retryUrl }}">
                <span class="btn__label">{{ __('admin.unavailable.retry') }}</span>
                <span class="btn__icon"><x-icon name="refresh" /></span>
            </a>
            <div class="adm-cluster">
                @if (! $outage && $signedIn)
                    <a class="adm-login__link" href="{{ route('admin.maintenance') }}">
                        <x-admin.icon name="database" />
                        <span>{{ __('admin.unavailable.maintenance') }}</span>
                    </a>
                @endif
                <a class="adm-login__link" href="{{ route('home') }}">
                    <x-admin.icon name="home" />
                    <span>{{ __('admin.unavailable.site') }}</span>
                </a>
            </div>
        </div>
    </div>
@endsection
