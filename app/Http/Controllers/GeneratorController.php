<?php

namespace App\Http\Controllers;

use App\Services\ArtEngine;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * "Atelier numérique": the generator page and the SVG art endpoint
 * (docs/ARCHITECTURE.md §3 — GET /atelier-numerique/oeuvre.svg).
 */
final class GeneratorController extends Controller
{
    public const DEFAULT_STYLE = 'pehouet';

    public const DEFAULT_SEED = 'pehouet';

    public const DEFAULT_SIZE = 800;

    public const MAX_SEED_LENGTH = 60;

    public function show(): View
    {
        $styles = $this->styles();

        return view('pages.generator', [
            'styles' => $styles,
            'defaultStyle' => in_array(self::DEFAULT_STYLE, $styles, true) ? self::DEFAULT_STYLE : ($styles[0] ?? self::DEFAULT_STYLE),
            'sizes' => $this->sizes(),
            'artUrl' => route('generator.art'),
        ]);
    }

    /**
     * Query: style, seed (≤ 60 chars), size, animate (0/1), download (0/1).
     * Invalid input ⇒ 422 text/plain listing every problem.
     */
    public function art(Request $request, ArtEngine $engine): Response
    {
        $errors = [];

        $style = $request->query('style') ?? self::DEFAULT_STYLE;
        if (! is_string($style) || ! in_array($style, $this->styles(), true)) {
            $errors[] = 'style: expected one of '.implode(', ', $this->styles()).'.';
        }

        $seed = $request->query('seed') ?? self::DEFAULT_SEED;
        if (! is_string($seed) || ! mb_check_encoding($seed, 'UTF-8')
            || mb_strlen($seed) > self::MAX_SEED_LENGTH || preg_match('/\p{Cc}/u', $seed) !== 0) {
            $errors[] = 'seed: expected text of at most '.self::MAX_SEED_LENGTH.' characters, without control characters.';
        }

        $size = $request->query('size') ?? (string) self::DEFAULT_SIZE;
        if (! is_string($size) || ! ctype_digit($size) || ! in_array((int) $size, $this->sizes(), true)) {
            $errors[] = 'size: expected one of '.implode(', ', $this->sizes()).'.';
        }

        $animate = $this->flag($request->query('animate'));
        $download = $this->flag($request->query('download'));
        if ($animate === null || $download === null) {
            $errors[] = 'animate, download: expected 0 or 1.';
        }

        if ($errors !== []) {
            return response(implode("\n", $errors)."\n", 422, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        /** @var string $style */
        /** @var string $seed */
        /** @var string $size */
        $art = $engine->render($style, $seed, (int) $size, (bool) $animate);

        $response = response($art['svg'], 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            // s-maxage lets Vercel's CDN serve repeat requests (same query, same artwork).
            'Cache-Control' => 'public, max-age=604800, s-maxage=604800',
            'X-Art-Engine' => $art['source'],
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);

        if ($download) {
            $name = Str::slug($seed) ?: 'oeuvre';
            $response->headers->set('Content-Disposition', 'attachment; filename="pehouet-'.$style.'-'.$name.'.svg"');
        }

        $response->setEtag(hash('xxh128', $art['svg']));
        $response->isNotModified($request);

        return $response;
    }

    /** "0"/"1" (or absent) ⇒ bool; anything else ⇒ null. */
    private function flag(mixed $value): ?bool
    {
        return match ($value) {
            null, '0' => false,
            '1' => true,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function styles(): array
    {
        return array_values(array_filter((array) config('atelier.art_styles', []), 'is_string'));
    }

    /**
     * @return list<int>
     */
    private function sizes(): array
    {
        return array_values(array_map('intval', (array) config('atelier.art_engine.sizes', [self::DEFAULT_SIZE])));
    }
}
