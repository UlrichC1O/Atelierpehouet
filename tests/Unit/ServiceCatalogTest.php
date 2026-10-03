<?php

namespace Tests\Unit;

use App\Support\ServiceCatalog;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    private function catalog(): ServiceCatalog
    {
        return new ServiceCatalog(base_path('tests/Fixtures/services'));
    }

    public function test_it_loads_valid_files_sorted_by_order_and_skips_invalid_ones(): void
    {
        $this->assertSame(
            ['beta-portrait', 'gamma-logo', 'alpha-fresque', 'delta-vase', 'epsilon-lettre', 'zeta-atelier'],
            $this->catalog()->slugs(),
        );
    }

    public function test_localized_arrays_carry_the_contract_keys(): void
    {
        $service = $this->catalog()->find('alpha-fresque', 'fr');

        $this->assertSame('03', $service['number']);
        $this->assertSame(route('services.show', 'alpha-fresque'), $service['url']);
        $this->assertSame(__('ui.categories.peinture'), $service['category_label']);
        foreach (['title', 'short', 'tagline', 'intro', 'body', 'features', 'process', 'ideal_for', 'faq', 'scene_alt', 'meta_description'] as $key) {
            $this->assertArrayHasKey($key, $service);
        }
    }

    public function test_missing_english_falls_back_to_french(): void
    {
        $catalog = $this->catalog();

        $this->assertSame($catalog->find('delta-vase', 'fr')['title'], $catalog->find('delta-vase', 'en')['title']);
        $this->assertNull($catalog->find('does-not-exist'));
    }

    public function test_neighbors_wrap_around(): void
    {
        $catalog = $this->catalog();

        $this->assertSame('zeta-atelier', $catalog->neighbors('beta-portrait')['prev']['slug']);
        $this->assertSame('gamma-logo', $catalog->neighbors('beta-portrait')['next']['slug']);
        $this->assertSame('beta-portrait', $catalog->neighbors('zeta-atelier')['next']['slug']);
    }

    public function test_related_prefers_the_same_category(): void
    {
        $related = $this->catalog()->related('alpha-fresque', 3);

        $this->assertCount(3, $related);
        $this->assertSame('peinture', $related->first()['category']);
        $this->assertNotContains('alpha-fresque', $related->pluck('slug')->all());
    }

    public function test_without_the_cms_every_file_service_is_published_and_unmodified(): void
    {
        $catalog = $this->catalog();
        $service = $catalog->find('alpha-fresque');

        $this->assertSame([true, false, false], [$service['published'], $service['custom'], $service['modified']]);
        $this->assertSame((require base_path('tests/Fixtures/services/alpha-fresque.php'))['fr'], $catalog->fileData('alpha-fresque')['fr']);
        $this->assertNull($catalog->fileData('does-not-exist'));
        $this->assertFalse($catalog->isCustom('alpha-fresque'));
        $this->assertSame($catalog->slugs(), $catalog->withHidden()->slugs());
        $this->assertSame($catalog->withHidden(), $catalog->withHidden()->withHidden());
    }

    public function test_by_category_follows_the_configured_order(): void
    {
        $keys = $this->catalog()->byCategory()->keys()->all();
        $configured = array_values(array_intersect(array_keys(config('atelier.categories')), $keys));

        $this->assertSame($configured, $keys);
    }
}
