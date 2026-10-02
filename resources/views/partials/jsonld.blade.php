{{-- Organization + WebSite structured data (schema.org), built from config('atelier'). --}}
@php
    $contact = config('atelier.contact', []);
    $organization = array_filter([
        '@type' => 'Organization',
        '@id' => url('/').'#organization',
        'name' => config('atelier.name'),
        'slogan' => __('ui.tagline'),
        'url' => url('/'),
        'logo' => asset('images/logo-ateliers-pehouet.png'),
        'image' => asset('images/og-ateliers-pehouet.jpg'),
        'email' => ($contact['email'] ?? '') ?: null,
        'telephone' => ($contact['phone'] ?? '') ?: null,
        'address' => ($contact['address'] ?? '') ?: null,
        'sameAs' => array_values(array_filter((array) config('atelier.socials', []))) ?: null,
    ]);
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $organization,
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'name' => config('atelier.name'),
                'url' => url('/'),
                'inLanguage' => ['fr', 'en'],
                'publisher' => ['@id' => url('/').'#organization'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
