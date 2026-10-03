<?php

namespace Tests\Unit\Cms;

use App\Cms\Markdown;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/** Markdown of the free pages: the only CMS HTML printed unescaped (docs/CMS.md §4.6, §13 F28). */
class MarkdownTest extends TestCase
{
    public function test_markdown_is_rendered(): void
    {
        $html = Markdown::render("## Mentions légales\n\nÉditeur : **Ateliers Pehouet**.\n\n- un\n- deux\n\n[Contact](https://pehouet.fr/contact)");

        $this->assertInstanceOf(HtmlString::class, $html);
        $this->assertStringContainsString('<h2>Mentions légales</h2>', (string) $html);
        $this->assertStringContainsString('<strong>Ateliers Pehouet</strong>', (string) $html);
        $this->assertStringContainsString('<li>deux</li>', (string) $html);
        $this->assertStringContainsString('<a href="https://pehouet.fr/contact">Contact</a>', (string) $html);
    }

    public function test_raw_html_and_unsafe_links_are_neutralised(): void
    {
        $html = (string) Markdown::render("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[clic](javascript:alert(1)) [data](data:text/html;base64,PHNjcmlwdD4=)");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_only_photos_of_the_library_stay_images_and_they_load_lazily(): void
    {
        $key = str_repeat('a', 26);
        $html = (string) Markdown::render(implode("\n\n", [
            '![Fresque de l’école](/media/'.$key.'-960.webp "Atelier")',
            '![Même photo, adresse complète]('.url('/media/'.$key.'.webp').')',
            '![Pixel espion](https://evil.example/p.gif)',
            '![](https://evil.example/sans-texte.png)',
            '![Ailleurs](//evil.example/media/'.$key.'.webp)',
            '![Faux chemin](/media/../admin.webp)',
            '![Script](javascript:alert(1))',
        ]));

        $this->assertStringContainsString('<img loading="lazy" decoding="async" src="/media/'.$key.'-960.webp" alt="Fresque de l’école" title="Atelier" />', $html);
        $this->assertStringContainsString('src="'.url('/media/'.$key.'.webp').'"', $html);
        $this->assertSame(2, substr_count($html, '<img'), 'every other image became a link');
        $this->assertStringContainsString('<a href="https://evil.example/p.gif">Pixel espion</a>', $html);
        $this->assertStringContainsString('<a href="https://evil.example/sans-texte.png">https://evil.example/sans-texte.png</a>', $html);
        $this->assertStringContainsString('<a href="//evil.example/media/'.$key.'.webp">Ailleurs</a>', $html);
        $this->assertStringContainsString('<a href="/media/../admin.webp">Faux chemin</a>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_media_urls_are_recognised_on_this_origin_only(): void
    {
        $key = str_repeat('b', 26);

        $this->assertTrue(Markdown::isMediaUrl('/media/'.$key.'.jpg'));
        $this->assertTrue(Markdown::isMediaUrl(url('/media/'.$key.'-480.png')));
        $this->assertFalse(Markdown::isMediaUrl('/media/'.$key.'.svg'));
        $this->assertFalse(Markdown::isMediaUrl('https://evil.example/media/'.$key.'.jpg'));
        $this->assertFalse(Markdown::isMediaUrl(url('/media/'.$key.'.jpg').'?x=1'));
        $this->assertFalse(Markdown::isMediaUrl('media/'.$key.'.jpg'));
    }

    public function test_empty_text_renders_nothing(): void
    {
        $this->assertSame('', (string) Markdown::render(null));
        $this->assertSame('', (string) Markdown::render("  \n "));
    }
}
