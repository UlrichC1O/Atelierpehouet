{{--
    FR / EN switch of the admin: the same page with ?lang= (SetLocale remembers it in the session).
    On a re-rendered POST/PUT (e.g. a 422 form), it points to the page the form came from.
--}}
@php
    $currentLocale = app()->getLocale();
    $request = request();
    $base = null;

    if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
        $previous = url()->previous();
        $base = is_string($previous) && str_starts_with($previous, url('/admin')) ? $previous : route('admin.dashboard');
    }

    $langUrl = function (string $code) use ($request, $base): string {
        if ($base === null) {
            return $request->fullUrlWithQuery(['lang' => $code]);
        }

        $parts = parse_url($base);
        parse_str($parts['query'] ?? '', $query);
        $query['lang'] = $code;

        return strtok($base, '?').'?'.http_build_query($query);
    };
@endphp
<div class="adm-lang-switch" role="group" aria-label="{{ __('admin.a11y.language') }}">
    @foreach (config('atelier.locales', []) as $code => $languageName)
        <a class="adm-lang-switch__link" href="{{ $langUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $languageName }}" @if ($code === $currentLocale) aria-current="true" @endif>
            <span aria-hidden="true">{{ strtoupper($code) }}</span><span class="visually-hidden">{{ $languageName }}</span>
        </a>
    @endforeach
</div>
