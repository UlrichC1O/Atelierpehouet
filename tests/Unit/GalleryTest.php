<?php

namespace Tests\Unit;

use App\Support\Gallery;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    public function test_only_safe_existing_artworks_with_known_styles_are_listed(): void
    {
        $gallery = new Gallery(base_path('tests/Fixtures/generated/gallery/manifest.json'));
        $files = collect($gallery->all())->pluck('src')->map(fn ($src) => basename($src))->all();

        $this->assertContains('prisme-1.svg', $files);
        $this->assertContains('pehouet-1.svg', $files);
        $this->assertNotContains('missing-1.svg', $files);
        $this->assertNotContains('unknown-1.svg', $files);
        $this->assertNotContains('evil.svg', $files);
    }

    public function test_titles_are_localized(): void
    {
        app()->setLocale('en');
        $gallery = new Gallery(base_path('tests/Fixtures/generated/gallery/manifest.json'));
        $titles = collect($gallery->all())->pluck('title')->all();

        $this->assertContains('Dawn prism', $titles);
        $this->assertContains('Grille rouge', $titles);
    }

    public function test_the_real_gallery_is_populated(): void
    {
        $gallery = app(Gallery::class);

        $this->assertGreaterThanOrEqual(8, count($gallery->all()));
        $this->assertCount(8, $gallery->preview(8));
    }
}
