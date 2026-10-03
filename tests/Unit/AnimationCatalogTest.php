<?php

namespace Tests\Unit;

use App\Support\AnimationCatalog;
use Tests\TestCase;

class AnimationCatalogTest extends TestCase
{
    public function test_entries_get_a_localized_title_with_french_fallback(): void
    {
        app()->setLocale('en');
        $catalog = new AnimationCatalog(base_path('tests/Fixtures/animations.json'));
        $byName = collect($catalog->all())->keyBy('name');

        $this->assertSame('Triangle reveal', $byName['ap-rv-tri']['title']);
        $this->assertSame('Néon TELIERS', $byName['ap-tx-neon']['title']);
        $this->assertGreaterThan(0, $catalog->total());
        $this->assertNotEmpty($catalog->forScene('alpha-fresque'));
        $this->assertArrayHasKey('scene', $catalog->grouped());
    }

    public function test_missing_or_invalid_files_give_an_empty_catalog(): void
    {
        foreach (['tests/Fixtures/animations-invalid.json', 'tests/Fixtures/nope.json'] as $path) {
            $catalog = new AnimationCatalog(base_path($path));
            $this->assertSame([], $catalog->all());
            $this->assertSame(0, $catalog->total());
        }
    }

    public function test_the_real_catalog_has_more_than_one_hundred_animations(): void
    {
        $this->assertGreaterThanOrEqual(100, app(AnimationCatalog::class)->total());
    }
}
