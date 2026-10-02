{{--
    Plain-text part of mail/contact-message.blade.php (App\Mail\ContactMessageReceived).
    This part is text/plain, never rendered as HTML: values are printed raw on purpose,
    otherwise an apostrophe would arrive as "&#039;".
--}}
@php
    $line = fn (string $field, ?string $text): string => __('mail.contact.line', [
        'label' => __('mail.contact.fields.'.$field),
        'value' => filled($text) ? $text : __('mail.contact.not_specified'),
    ]);
@endphp
ATELIERS PEHOUET — {!! __('mail.contact.tagline') !!}

{!! __('mail.contact.heading') !!}

{!! __('mail.contact.intro', ['name' => $contactMessage->name, 'date' => $receivedAt]) !!}

{!! $line('name', $contactMessage->name) !!}
{!! $line('email', $contactMessage->email) !!}
{!! $line('phone', $contactMessage->phone) !!}
{!! $line('service', $serviceTitle ? $serviceTitle.($serviceUrl ? ' — '.$serviceUrl : '') : null) !!}
{!! $line('budget', $budgetLabel) !!}
{!! $line('locale', $localeName) !!}

{!! __('mail.contact.fields.message') !!}
----------------------------------------
{!! $contactMessage->message !!}
----------------------------------------

{!! __('mail.contact.footer', ['site' => $siteUrl, 'name' => $contactMessage->name]) !!}
