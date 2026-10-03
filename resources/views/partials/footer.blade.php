{{--
    Site footer: statement + CTA (only when the page has no closing <x-cta-band> of its own, and
    never on the contact page), the services by category, pages, contact (always a way to write to
    the atelier, plus the details that are configured), socials (only when configured), motion
    toggle and language switch.
    Data from App\View\Composers\NavigationComposer: $navServices, $navCategories.
--}}
@php
    $navServices = $navServices ?? collect();
    $navCategories = $navCategories ?? [];
    $byCategory = collect($navCategories)
        ->map(fn ($label, $key) => ['label' => $label, 'accent' => config("atelier.categories.$key.accent", 'yellow'), 'items' => $navServices->where('category', $key)->values()])
        ->filter(fn ($group) => $group['items']->isNotEmpty());
    $contact = config('atelier.contact', []);
    $socials = array_filter((array) config('atelier.socials', []));
    $whatsapp = preg_replace('/\D+/', '', (string) ($contact['whatsapp'] ?? ''));
    $explore = array_filter([
        'home' => __('ui.nav.home'),
        'services.index' => __('ui.nav.services'),
        'gallery' => __('ui.nav.gallery'),
        'artists.index' => \Illuminate\Support\Facades\Route::has('artists.index')
            ? (\Illuminate\Support\Facades\Lang::has('artists.nav') ? __('artists.nav') : __('ui.nav.artists'))
            : null,
        'community' => __('ui.nav.community'),
        'about' => __('ui.nav.about'),
        'generator' => __('ui.nav.generator'),
        'motion' => __('ui.nav.motion'),
        'contact' => __('ui.nav.contact'),
    ]);
    // A page that ends with its own call to action (components/cta-band) doesn't get a second one.
    $showCta = ! ($apCtaBandShown ?? false) && ! request()->routeIs('contact');
@endphp
<footer @class(['site-footer', 'site-footer--after-band' => $apCtaBandShown ?? false])>
    <div class="site-footer__mondrian" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span></div>

    <div class="container container--wide">
        @if ($showCta)
            <div class="site-footer__cta">
                <p class="site-footer__statement" aria-hidden="true">{{ __('ui.footer.statement') }}</p>
                <div class="site-footer__cta-side">
                    <h2 class="visually-hidden">{{ __('ui.footer.statement') }}</h2>
                    <p class="site-footer__text">{{ __('ui.footer.text') }}</p>
                    <x-button :href="route('contact')" size="lg" icon="arrow-right" magnetic>{{ __('ui.cta.quote') }}</x-button>
                </div>
            </div>
        @endif

        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <a href="{{ route('home') }}" class="site-footer__home" aria-label="{{ __('ui.a11y.home') }}">
                    <x-logo-wordmark size="footer" tagline />
                </a>
                <p class="site-footer__note">{{ __('ui.footer.quote_note') }}</p>
                <x-signature class="site-footer__signature" />
            </div>

            <nav class="site-footer__services" aria-label="{{ __('ui.a11y.services_nav') }}">
                @foreach ($byCategory as $group)
                    <div class="accent-{{ $group['accent'] }}">
                        <p class="site-footer__group-title">{{ $group['label'] }}</p>
                        <ul class="site-footer__list" role="list">
                            @foreach ($group['items'] as $service)
                                <li><a href="{{ $service['url'] }}">{{ $service['title'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <nav aria-label="{{ __('ui.a11y.footer_nav') }}">
                <p class="site-footer__heading">{{ __('ui.footer.explore') }}</p>
                <ul class="site-footer__list" role="list">
                    @foreach ($explore as $route => $label)
                        <li><a href="{{ route($route) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div>
                <p class="site-footer__heading">{{ __('ui.footer.contact') }}</p>
                <address class="site-footer__contact">
                    <a href="{{ route('contact') }}"><x-icon name="arrow-right" />{{ __('ui.footer.write') }}</a>
                    @if (! empty($contact['email']))
                        <a href="mailto:{{ $contact['email'] }}"><x-icon name="mail" /><span class="visually-hidden">{{ __('ui.contact.email') }} : </span>{{ $contact['email'] }}</a>
                    @endif
                    @if (! empty($contact['phone']))
                        <a href="tel:{{ preg_replace('/[^\d+]/', '', $contact['phone']) }}"><x-icon name="phone" /><span class="visually-hidden">{{ __('ui.contact.phone') }} : </span>{{ $contact['phone'] }}</a>
                    @endif
                    @if ($whatsapp !== '')
                        <a href="https://wa.me/{{ $whatsapp }}" rel="noopener" target="_blank"><x-icon name="whatsapp" />{{ __('ui.contact.whatsapp') }}<span class="visually-hidden"> ({{ __('ui.a11y.new_tab') }})</span></a>
                    @endif
                    @if (! empty($contact['address']))
                        <span><x-icon name="map-pin" />{{ $contact['address'] }}</span>
                    @endif
                </address>

                @if ($socials)
                    <p class="site-footer__heading" style="margin-top: 1.5rem">{{ __('ui.footer.follow') }}</p>
                    <ul class="site-footer__socials" role="list" aria-label="{{ __('ui.a11y.social') }}">
                        @foreach ($socials as $network => $url)
                            <li>
                                <a class="site-footer__social" href="{{ $url }}" rel="noopener me" target="_blank" title="{{ __('ui.social.'.$network) }}">
                                    <x-icon :name="$network" /><span class="visually-hidden">{{ __('ui.social.'.$network) }} ({{ __('ui.a11y.new_tab') }})</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="site-footer__tools">
                    @include('partials.lang-switch')
                    @include('partials.motion-toggle')
                </div>
            </div>
        </div>

        <div class="site-footer__bottom">
            <p>© {{ now()->year }} {{ config('atelier.name') }} — {{ __('ui.tagline') }}. {{ __('ui.footer.rights') }}</p>
            <p><a href="{{ route('sitemap') }}">sitemap.xml</a></p>
        </div>
    </div>
</footer>
