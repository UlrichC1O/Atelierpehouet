<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** GET /atelier-numerique/oeuvre.svg — the bridge to the Python art engine (§3, §11). */
class ArtEndpointTest extends TestCase
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#000000"/></svg>';

    public function test_it_serves_the_python_http_engine_output(): void
    {
        config(['atelier.art_engine.url' => 'http://art.test', 'atelier.art_engine.cli' => false]);
        Http::fake(['art.test/*' => Http::response(self::SVG, 200, ['Content-Type' => 'image/svg+xml'])]);

        $this->get(route('generator.art', ['style' => 'vitrail', 'seed' => 'Quartier', 'size' => 400]))
            ->assertOk()
            ->assertHeader('X-Art-Engine', 'http')
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->assertSee('<svg', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'style=vitrail') && str_contains($request->url(), 'seed=Quartier'));
    }

    public function test_it_falls_back_to_the_python_cli(): void
    {
        config(['atelier.art_engine.url' => '', 'atelier.art_engine.cli' => true]);
        Process::fake(['*' => Process::result(self::SVG)]);

        $this->get(route('generator.art', ['style' => 'soleil', 'seed' => 'Aube']))
            ->assertOk()
            ->assertHeader('X-Art-Engine', 'cli');

        Process::assertRan(fn ($process) => in_array('art_engine', (array) $process->command, true) || str_contains(implode(' ', (array) $process->command), 'art_engine'));
    }

    public function test_it_falls_back_to_built_in_art_when_python_is_unavailable(): void
    {
        config(['atelier.art_engine.url' => '', 'atelier.art_engine.cli' => false]);

        $response = $this->get(route('generator.art', ['style' => 'pehouet', 'seed' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertHeader('X-Art-Engine', 'fallback');

        $svg = $response->getContent();
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<script', $svg);
        simplexml_load_string($svg) ?: $this->fail('Fallback SVG is not valid XML');
    }

    public function test_rejected_engine_output_is_never_served(): void
    {
        config(['atelier.art_engine.url' => 'http://art.test', 'atelier.art_engine.cli' => false]);
        Http::fake(['art.test/*' => Http::response('<svg><script>alert(1)</script></svg>', 200)]);

        $response = $this->get(route('generator.art', ['style' => 'pehouet', 'seed' => 'x']))->assertOk();
        $this->assertSame('fallback', $response->headers->get('X-Art-Engine'));
        $this->assertStringNotContainsString('<script', $response->getContent());
    }

    public function test_art_responses_are_cacheable_and_locked_down(): void
    {
        config(['atelier.art_engine.url' => '', 'atelier.art_engine.cli' => false]);

        $response = $this->get(route('generator.art', ['style' => 'mondrian', 'seed' => 'abc']))->assertOk();
        $this->assertStringContainsString("default-src 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('max-age=604800', (string) $response->headers->get('Cache-Control'));
    }

    public function test_download_sets_an_attachment_filename(): void
    {
        config(['atelier.art_engine.url' => '', 'atelier.art_engine.cli' => false]);

        $this->get(route('generator.art', ['style' => 'eclats', 'seed' => 'Fête du quartier', 'download' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="pehouet-eclats-fete-du-quartier.svg"');
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        foreach ([['style' => 'cubisme'], ['size' => 123], ['seed' => str_repeat('x', 61)], ['animate' => 'yes']] as $query) {
            $this->get(route('generator.art', $query))->assertStatus(422);
        }
    }
}
