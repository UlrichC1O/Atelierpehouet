{{--
    Ateliers Pehouet — e-mail sent to the atelier for each contact / quote request
    (App\Mail\ContactMessageReceived). E-mail clients ignore stylesheets: inline styles,
    tables, logo palette only. Never name a variable $message here (reserved by Laravel).
--}}
@php
    $font = "font-family:'Trebuchet MS',Verdana,Geneva,sans-serif;";
    $label = $font.'font-size:11px;line-height:16px;letter-spacing:2px;text-transform:uppercase;color:#898789;padding:10px 16px 10px 0;vertical-align:top;white-space:nowrap;';
    $value = $font.'font-size:15px;line-height:22px;color:#fafcfd;padding:10px 0;vertical-align:top;';
    $link = 'color:#f8d449;text-decoration:underline;';
    $none = '<span style="color:#898789;">'.e(__('mail.contact.not_specified')).'</span>';
    $letters = [['A', '#fafcfd'], ['T', '#e3a94e'], ['E', '#c96338'], ['L', '#c96338'], ['I', '#e8433b'], ['E', '#e8433b'], ['R', '#b32c2b'], ['S', '#b32c2b']];
    $replySubject = rawurlencode('Re: '.__('mail.contact.subject', ['name' => $contactMessage->name]));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>{{ __('mail.contact.subject', ['name' => $contactMessage->name]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#000000;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#000000;">{{ __('mail.contact.preheader') }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#000000;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#08080b;border:1px solid #2c2c34;">

                    {{-- Mondrian strip: the triangle's colour fields with white seams --}}
                    <tr>
                        <td style="padding:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="34%" height="10" style="height:10px;background-color:#265fa5;border-right:3px solid #fafcfd;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="24%" height="10" style="height:10px;background-color:#f8d449;border-right:3px solid #fafcfd;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="8%" height="10" style="height:10px;background-color:#fafcfd;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="22%" height="10" style="height:10px;background-color:#b32c2b;border-left:3px solid #fafcfd;font-size:0;line-height:0;">&nbsp;</td>
                                    <td width="12%" height="10" style="height:10px;background-color:#000000;border-left:3px solid #fafcfd;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Wordmark: TELIERS in its gradient colours, letter by letter --}}
                    <tr>
                        <td style="padding:30px 32px 0;">
                            <p style="margin:0;{{ $font }}font-size:26px;line-height:30px;font-weight:700;letter-spacing:5px;">@foreach ($letters as [$letter, $colour])<span style="color:{{ $colour }};">{{ $letter }}</span>@endforeach<span style="{{ $font }}font-size:17px;font-weight:400;font-style:italic;letter-spacing:1px;color:#fafcfd;"> Pehouet</span></p>
                            <p style="margin:6px 0 0;{{ $font }}font-size:13px;line-height:18px;color:#898789;">{{ __('mail.contact.tagline') }}</p>
                        </td>
                    </tr>

                    {{-- Heading --}}
                    <tr>
                        <td style="padding:30px 32px 0;">
                            <p style="margin:0 0 8px;{{ $font }}font-size:11px;line-height:16px;letter-spacing:3px;text-transform:uppercase;color:#e3a94e;">{{ __('mail.contact.eyebrow') }}</p>
                            <h1 style="margin:0;{{ $font }}font-size:26px;line-height:32px;font-weight:700;color:#fafcfd;">{{ __('mail.contact.heading') }}</h1>
                            <p style="margin:12px 0 0;{{ $font }}font-size:15px;line-height:22px;color:#c4c2c5;">{{ __('mail.contact.intro', ['name' => $contactMessage->name, 'date' => $receivedAt]) }}</p>
                        </td>
                    </tr>

                    {{-- Details --}}
                    <tr>
                        <td style="padding:20px 32px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #2c2c34;">
                                <tr>
                                    <td style="{{ $label }}border-bottom:1px solid #1d1d23;">{{ __('mail.contact.fields.name') }}</td>
                                    <td style="{{ $value }}border-bottom:1px solid #1d1d23;">{{ $contactMessage->name }}</td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}border-bottom:1px solid #1d1d23;">{{ __('mail.contact.fields.email') }}</td>
                                    <td style="{{ $value }}border-bottom:1px solid #1d1d23;"><a href="mailto:{{ $contactMessage->email }}" style="{{ $link }}">{{ $contactMessage->email }}</a></td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}border-bottom:1px solid #1d1d23;">{{ __('mail.contact.fields.phone') }}</td>
                                    <td style="{{ $value }}border-bottom:1px solid #1d1d23;">
                                        @if ($contactMessage->phone)
                                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contactMessage->phone) }}" style="{{ $link }}">{{ $contactMessage->phone }}</a>
                                        @else
                                            {!! $none !!}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}border-bottom:1px solid #1d1d23;">{{ __('mail.contact.fields.service') }}</td>
                                    <td style="{{ $value }}border-bottom:1px solid #1d1d23;">
                                        @if ($serviceTitle && $serviceUrl)
                                            <a href="{{ $serviceUrl }}" style="{{ $link }}">{{ $serviceTitle }}</a>
                                        @elseif ($serviceTitle)
                                            {{ $serviceTitle }}
                                        @else
                                            {!! $none !!}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}border-bottom:1px solid #1d1d23;">{{ __('mail.contact.fields.budget') }}</td>
                                    <td style="{{ $value }}border-bottom:1px solid #1d1d23;">
                                        @if ($budgetLabel)
                                            {{ $budgetLabel }}
                                        @else
                                            {!! $none !!}
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="{{ $label }}">{{ __('mail.contact.fields.locale') }}</td>
                                    <td style="{{ $value }}">{{ $localeName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Message --}}
                    <tr>
                        <td style="padding:16px 32px 0;">
                            <p style="margin:0 0 10px;{{ $font }}font-size:11px;line-height:16px;letter-spacing:2px;text-transform:uppercase;color:#898789;">{{ __('mail.contact.fields.message') }}</p>
                            <div style="background-color:#121216;border-left:4px solid #b32c2b;padding:18px 20px;{{ $font }}font-size:15px;line-height:24px;color:#fafcfd;">{!! nl2br(e($contactMessage->message), false) !!}</div>
                        </td>
                    </tr>

                    {{-- Reply --}}
                    <tr>
                        <td style="padding:28px 32px 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color:#f8d449;">
                                        <a href="mailto:{{ $contactMessage->email }}?subject={{ $replySubject }}" style="display:inline-block;padding:13px 24px;{{ $font }}font-size:14px;line-height:18px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#000000;text-decoration:none;">{{ __('mail.contact.reply', ['name' => $contactMessage->name]) }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:18px 32px 24px;border-top:1px solid #2c2c34;{{ $font }}font-size:12px;line-height:18px;color:#898789;">
                            {{ __('mail.contact.footer', ['site' => $siteUrl, 'name' => $contactMessage->name]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
