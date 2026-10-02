{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
{{-- Ateliers Pehouet — sitemap (SitemapController@index).
     $urls: list of ['loc' => absolute URL, 'lastmod' => W3C date|null, 'alternates' => [hreflang => absolute URL]] --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
@if ($entry['lastmod'])
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
@endif
@foreach ($entry['alternates'] as $hreflang => $href)
        <xhtml:link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}"/>
@endforeach
    </url>
@endforeach
</urlset>
