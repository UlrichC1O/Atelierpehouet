<?php

namespace App\Http\Controllers;

use App\Support\ServiceCatalog;
use Illuminate\Http\Response;

/**
 * sitemap.xml (every page and service in every language, absolute URLs, hreflang
 * alternates) and robots.txt.
 */
final class SitemapController extends Controller
{
    /**
     * Pages: route name ⇒ files whose modification time is the page's lastmod.
     *
     * @var array<string, list<string>>
     */
    private const PAGES = [
        'home' => ['views/pages/home.blade.php', 'lang/fr/home.php'],
        'services.index' => ['views/services/index.blade.php', 'lang/fr/services.php'],
        'about' => ['views/pages/about.blade.php', 'lang/fr/about.php'],
        'community' => ['views/pages/community.blade.php', 'lang/fr/community.php'],
        'gallery' => ['views/pages/gallery.blade.php', 'lang/fr/gallery.php'],
        'generator' => ['views/pages/generator.blade.php', 'lang/fr/generator.php'],
        'motion' => ['views/pages/motion.blade.php', 'lang/fr/motion.php'],
        'contact' => ['views/pages/contact.blade.php', 'lang/fr/contact.php'],
    ];

    public function index(ServiceCatalog $catalog): Response
    {
        $pages = [];

        foreach (self::PAGES as $route => $files) {
            $pages[route($route)] = $this->lastModified(array_map($this->path(...), $files));
        }

        foreach ($catalog->slugs() as $slug) {
            $pages[route('services.show', ['slug' => $slug])] = $this->lastModified([
                $catalog->path($slug),
                resource_path('views/services/scenes/'.$slug.'.blade.php'),
                resource_path('views/services/show.blade.php'),
            ]);
        }

        // One entry per language version, each listing all of them (hreflang).
        $urls = [];

        foreach ($pages as $loc => $lastmod) {
            $alternates = $this->alternates($loc);

            foreach (array_diff_key($alternates, ['x-default' => true]) as $href) {
                $urls[] = ['loc' => $href, 'lastmod' => $lastmod, 'alternates' => $alternates];
            }
        }

        return response()
            ->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=utf-8'])
            ->header('Cache-Control', 'public, max-age=3600, s-maxage=3600');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /atelier-numerique/oeuvre.svg',
            'Disallow: /langue/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600, s-maxage=3600',
        ]);
    }

    /**
     * Language versions of a page, as declared by the layout: the default language
     * on the clean URL (also x-default), the others with ?lang=.
     *
     * @return array<string, string> hreflang ⇒ absolute URL
     */
    private function alternates(string $loc): array
    {
        $locales = array_keys((array) config('atelier.locales', ['fr' => 'Français']));
        $default = array_shift($locales);
        $alternates = [$default => $loc];

        foreach ($locales as $locale) {
            $alternates[$locale] = $loc.'?lang='.$locale;
        }

        return $alternates + ['x-default' => $loc];
    }

    private function path(string $relative): string
    {
        return str_starts_with($relative, 'lang/')
            ? lang_path(substr($relative, 5))
            : resource_path($relative);
    }

    /**
     * W3C date of the most recently modified existing file, or null.
     *
     * @param  list<string|null>  $files
     */
    private function lastModified(array $files): ?string
    {
        $times = array_map(
            fn (string $file): int => (int) filemtime($file),
            array_filter($files, fn (?string $file): bool => $file !== null && is_file($file)),
        );

        return $times === [] ? null : date(DATE_ATOM, max($times));
    }
}
