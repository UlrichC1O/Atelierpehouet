{{--
    "Copie de test" warning (docs/CMS.md §13 F25), on every admin page of a copy that is not the
    official site: shown when config('cms.primary_url') is set and differs from this site's url('/').
    The two hosts share no data (Codespace = SQLite, Vercel = Supabase).
--}}
@php
    $primaryUrl = rtrim(trim((string) config('cms.primary_url', '')), '/');
    $isCopy = $primaryUrl !== '' && preg_match('#^https?://#i', $primaryUrl) && strcasecmp(rtrim(url('/'), '/'), $primaryUrl) !== 0;
@endphp
@if ($isCopy)
    <div class="adm-copy" role="note">
        <span class="adm-copy__icon" aria-hidden="true"><x-admin.icon name="warning" /></span>
        <p class="adm-copy__text">
            {{ __('admin.copy.warning') }}
            <a class="adm-copy__link" href="{{ $primaryUrl }}/admin">{{ __('admin.copy.link') }}</a>
        </p>
    </div>
@endif
