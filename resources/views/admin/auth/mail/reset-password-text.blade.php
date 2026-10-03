{{--
    Plain-text part of admin/auth/mail/reset-password.blade.php (App\Notifications\AdminResetPassword).
    This part is text/plain, never rendered as HTML: values are printed raw on purpose, otherwise an
    apostrophe would arrive as "&#039;".
--}}
{!! __('admin.password.mail.greeting', ['name' => $name]) !!}

{!! __('admin.password.mail.intro', ['site' => $site]) !!}

{!! __('admin.password.mail.action') !!}
{!! $url !!}

{!! __('admin.password.mail.expire', ['minutes' => $minutes]) !!}
{!! __('admin.password.mail.ignore') !!}

{!! __('admin.password.mail.salutation', ['site' => $site]) !!}
