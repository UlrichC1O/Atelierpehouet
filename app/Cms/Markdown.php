<?php

namespace App\Cms;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;

/**
 * Markdown of the free pages (docs/CMS.md §4.6, §13 F28) — the only CMS output a view may print
 * unescaped ({!! !!}): raw HTML is stripped and javascript:/data: style links are disabled.
 *
 * Images are kept only when they are photos of this site's library — a same-origin /media/{key}
 * URL, as "Insérer une photo" writes them — and load lazily; any other image becomes a plain link
 * to its URL (no hotlinked or tracking images, the CSP only allows this site's images anyway).
 */
final class Markdown implements ExtensionInterface
{
    /** Path of a library photo (MediaFileController's key pattern). */
    private const MEDIA_PATH = '#^/media/[0-9a-z]{26}(?:-[0-9]{2,4})?\.(?:jpg|png|webp|gif)$#';

    public static function render(?string $text): HtmlString
    {
        if ($text === null || trim($text) === '') {
            return new HtmlString('');
        }

        return new HtmlString(Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ], [new self]));
    }

    /** True for a photo of this site's library: /media/{key}, or the same on this site's origin. */
    public static function isMediaUrl(string $url): bool
    {
        if (preg_match(self::MEDIA_PATH, $url) === 1) {
            return true;
        }

        $parts = parse_url($url);
        $root = parse_url(url('/'));

        if (! is_array($parts) || ! is_array($root) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && strcasecmp($parts['host'] ?? '', $root['host'] ?? '') === 0
            && ($parts['port'] ?? null) === ($root['port'] ?? null)
            && preg_match(self::MEDIA_PATH, $parts['path'] ?? '') === 1;
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, $this->onDocumentParsed(...));
    }

    private function onDocumentParsed(DocumentParsedEvent $event): void
    {
        $images = [];
        $walker = $event->getDocument()->walker();

        while ($step = $walker->next()) {
            if ($step->isEntering() && $step->getNode() instanceof Image) {
                $images[] = $step->getNode();
            }
        }

        foreach ($images as $image) {
            if (self::isMediaUrl($image->getUrl())) {
                $image->data->set('attributes/loading', 'lazy');
                $image->data->set('attributes/decoding', 'async');

                continue;
            }

            $this->replaceByLink($image);
        }
    }

    /** An outside image becomes a link to it, labelled with its alt text (or its URL). */
    private function replaceByLink(Image $image): void
    {
        $link = new Link($image->getUrl(), null, $image->getTitle());
        $children = [...$image->children()];

        if ($children === []) {
            $link->appendChild(new Text($image->getUrl()));
        }

        foreach ($children as $child) {
            $link->appendChild($child);
        }

        $image->replaceWith($link);
    }
}
