{{--
    Footer links to the free pages published "in the footer" (docs/CMS.md §7.4), included by the
    public-site session in partials/footer.blade.php (§12). Reuses the footer's link classes; renders
    nothing when there is no such page (or no CMS data).
--}}
@php
    $footerPages = function_exists('cms') ? rescue(fn () => cms()->footerPages(), [], false) : [];
    $footerLocale = app()->getLocale();
@endphp
@if ($footerPages !== [])
    <nav class="site-footer__pages" aria-label="{{ __('pages.footer.label') }}">
        <p class="site-footer__heading">{{ __('pages.footer.heading') }}</p>
        <ul class="site-footer__list" role="list">
            @foreach ($footerPages as $footerPage)
                @php
                    $footerTitle = $footerPage['title'][$footerLocale] ?? null;
                    $footerTitle = is_string($footerTitle) && trim($footerTitle) !== '' ? $footerTitle : $footerPage['title']['fr'];
                @endphp
                <li><a href="{{ route('pages.custom', $footerPage['slug']) }}" @if (request()->routeIs('pages.custom') && request()->route('slug') === $footerPage['slug']) aria-current="page" @endif>{{ $footerTitle }}</a></li>
            @endforeach
        </ul>
    </nav>
@endif
