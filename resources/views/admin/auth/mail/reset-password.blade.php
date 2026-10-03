{{--
    E-mail "Mot de passe oublié ?" of the admin (App\Notifications\AdminResetPassword, docs/CMS.md §13 D19).
    Vars: $url (reset link), $name (account name), $site (brand name), $minutes (link lifetime).
    E-mail clients ignore stylesheets: inline styles, tables, logo palette only. Never name a variable
    $message here (reserved by Laravel). Plain-text part: reset-password-text.blade.php.
--}}
@php
    $font = "font-family:'Trebuchet MS',Verdana,Geneva,sans-serif;";
    $text = $font.'margin:0 0 14px;font-size:15px;line-height:23px;color:#c4c2c5;';
    $letters = [['A', '#fafcfd'], ['T', '#e3a94e'], ['E', '#c96338'], ['L', '#c96338'], ['I', '#e8433b'], ['E', '#e8433b'], ['R', '#b32c2b'], ['S', '#b32c2b']];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <title>{{ __('admin.password.mail.subject', ['site' => $site]) }}</title>
</head>
<body style="margin:0;padding:0;background-color:#000000;">
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
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 6px;">
                            <h1 style="margin:0 0 18px;{{ $font }}font-size:24px;line-height:30px;font-weight:700;color:#fafcfd;">{{ __('admin.password.reset_title') }}</h1>
                            <p style="{{ $text }}color:#fafcfd;">{{ __('admin.password.mail.greeting', ['name' => $name]) }}</p>
                            <p style="{{ $text }}">{{ __('admin.password.mail.intro', ['site' => $site]) }}</p>
                        </td>
                    </tr>

                    {{-- The button: a plain link styled as one (works in every client) --}}
                    <tr>
                        <td style="padding:6px 32px 22px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="background-color:#f8d449;border-radius:4px;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:13px 22px;{{ $font }}font-size:15px;line-height:20px;font-weight:700;color:#000000;text-decoration:none;">{{ __('admin.password.mail.action') }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 26px;">
                            <p style="{{ $text }}">{{ __('admin.password.mail.expire', ['minutes' => $minutes]) }}</p>
                            <p style="{{ $text }}">{{ __('admin.password.mail.ignore') }}</p>
                            <p style="{{ $font }}margin:18px 0 0;font-size:12px;line-height:18px;color:#898789;word-break:break-all;"><a href="{{ $url }}" style="color:#898789;text-decoration:underline;">{{ $url }}</a></p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 26px;border-top:1px solid #1d1d23;">
                            <p style="margin:0;{{ $font }}font-size:13px;line-height:19px;color:#898789;">{{ __('admin.password.mail.salutation', ['site' => $site]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
