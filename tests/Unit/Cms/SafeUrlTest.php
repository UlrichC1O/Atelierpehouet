<?php

namespace Tests\Unit\Cms;

use App\Cms\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** URLs from forms, query strings and settings (docs/CMS.md §13 E22): never an open redirect. */
class SafeUrlTest extends TestCase
{
    /** @return array<string, array{0: string|null}> */
    public static function hostileUrls(): array
    {
        return [
            'protocol-relative' => ['//evil.com'],
            'slash backslash' => ['/\\evil.com'],
            'double backslash' => ['\\\\evil.com'],
            'tab before //' => ["\t//evil.com"],
            'backslash before @' => ['https://evil.com\\@host/'],
            'javascript' => ['javascript://host/%0aalert(1)'],
            'another site' => ['https://evil.com/admin'],
            'same host prefix' => ['http://localhost.evil.com/'],
            'credentials' => ['http://localhost@evil.com/'],
            'no leading slash' => ['admin/photos'],
            'newline' => ["/admin\n/photos"],
            'non-breaking space' => ["/admin\u{00A0}"],
            'right-to-left override' => ["/admin\u{202E}"],
            'invalid utf-8' => ["/admin\xC3\x28"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'empty' => [''],
            'null' => [null],
        ];
    }

    #[DataProvider('hostileUrls')]
    public function test_internal_refuses_anything_but_this_site(?string $url): void
    {
        $this->assertSame('/fallback', SafeUrl::internal($url, '/fallback'));
    }

    public function test_internal_accepts_paths_and_absolute_urls_of_this_site(): void
    {
        config(['app.url' => 'http://localhost']);
        $root = rtrim(url('/'), '/');

        $this->assertSame($root.'/admin/photos?slot=home.feature&redirect=%2Fadmin#spot', SafeUrl::internal('/admin/photos?slot=home.feature&redirect=%2Fadmin#spot', '/fallback'));
        $this->assertSame($root, SafeUrl::internal('/', '/fallback'));
        $this->assertSame($root.'/admin/services/sculpture', SafeUrl::internal($root.'/admin/services/sculpture', '/fallback'));
        $this->assertSame($root, SafeUrl::internal($root, '/fallback'));
    }

    #[DataProvider('hostileUrls')]
    public function test_external_refuses_hostile_urls(?string $url): void
    {
        if ($url === 'https://evil.com/admin') {
            $this->assertSame($url, SafeUrl::external($url), 'a plain https link to another site is what external() is for');

            return;
        }

        $this->assertNull(SafeUrl::external($url));
    }

    public function test_external_accepts_https_links_only(): void
    {
        $this->assertSame('https://www.instagram.com/ateliers.pehouet', SafeUrl::external(' https://www.instagram.com/ateliers.pehouet '));
        $this->assertSame('https://wa.me/33611223344?text=Bonjour', SafeUrl::external('https://wa.me/33611223344?text=Bonjour'));
        $this->assertNull(SafeUrl::external('http://www.instagram.com/ateliers.pehouet'));
        $this->assertNull(SafeUrl::external('https://user:pass@www.instagram.com/'));
        $this->assertNull(SafeUrl::external('https:///no-host'));
        $this->assertNull(SafeUrl::external('www.instagram.com/ateliers.pehouet'));
    }

    public function test_link_takes_a_page_of_this_site_or_an_https_link(): void
    {
        $this->assertSame(url('/contact'), SafeUrl::link('/contact'));
        $this->assertSame('https://www.helloasso.com/evenement', SafeUrl::link('https://www.helloasso.com/evenement'));
        $this->assertNull(SafeUrl::link('//evil.com'));
        $this->assertNull(SafeUrl::link('javascript:alert(1)'));
        $this->assertNull(SafeUrl::link(''));
        $this->assertNull(SafeUrl::link(null));
    }
}
