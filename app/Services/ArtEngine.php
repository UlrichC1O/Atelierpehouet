<?php

namespace App\Services;

use App\Services\Art\FallbackArtwork;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bridge to the Python generative-art engine (python/art_engine, docs/ARCHITECTURE.md §11).
 *
 * Tries, in order: the HTTP micro-service (config atelier.art_engine.url), the engine's
 * CLI through Process (config atelier.art_engine.cli), then a deterministic PHP composition
 * (App\Services\Art\FallbackArtwork). Engine output is only accepted when it looks like a
 * plain SVG document. Never throws; failures are logged at debug level.
 */
final class ArtEngine
{
    /** Seconds the CLI may take to render one artwork. */
    private const CLI_TIMEOUT = 20;

    /** Largest SVG accepted from the engine (bytes). */
    private const MAX_BYTES = 8_000_000;

    /**
     * @return array{svg: string, source: 'http'|'cli'|'fallback'}
     */
    public function render(string $style, string $seed, int $size = 800, bool $animate = false): array
    {
        $svg = $this->viaHttp($style, $seed, $size, $animate);

        if ($svg !== null) {
            return ['svg' => $svg, 'source' => 'http'];
        }

        $svg = $this->viaCli($style, $seed, $size, $animate);

        if ($svg !== null) {
            return ['svg' => $svg, 'source' => 'cli'];
        }

        return ['svg' => $this->fallback($style, $seed, $size, $animate), 'source' => 'fallback'];
    }

    /** The built-in composition (no Python involved). */
    public function fallback(string $style, string $seed, int $size = 800, bool $animate = false): string
    {
        return (new FallbackArtwork($style, $seed, $size, $animate))->toSvg();
    }

    /**
     * True for a standalone SVG document without scripts, event handlers or entity
     * declarations.
     */
    public static function looksLikeSvg(string $body): bool
    {
        $body = ltrim($body, "\u{FEFF} \t\r\n");

        if (strlen($body) > self::MAX_BYTES || ! (str_starts_with($body, '<svg') || str_starts_with($body, '<?xml'))) {
            return false;
        }

        if (! str_contains($body, '</svg>')) {
            return false;
        }

        return preg_match('/<script|<!ENTITY|javascript:|<foreignObject|\son[a-z]+\s*=/i', $body) !== 1;
    }

    private function viaHttp(string $style, string $seed, int $size, bool $animate): ?string
    {
        $url = rtrim((string) config('atelier.art_engine.url', ''), '/');

        if ($url === '') {
            return null;
        }

        $timeout = max(0.1, (float) config('atelier.art_engine.timeout', 2.5));

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(1.0, $timeout))
                ->accept('image/svg+xml')
                ->get($url.'/art', [
                    'style' => $style,
                    'seed' => $seed,
                    'width' => $size,
                    'height' => $size,
                    'animate' => $animate ? 1 : 0,
                ]);
        } catch (Throwable $e) {
            Log::debug('Art engine HTTP request failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::debug('Art engine HTTP request returned status '.$response->status().'.');

            return null;
        }

        return $this->accept($response->body(), 'HTTP');
    }

    private function viaCli(string $style, string $seed, int $size, bool $animate): ?string
    {
        if (! config('atelier.art_engine.cli')) {
            return null;
        }

        $command = [
            (string) config('atelier.art_engine.python', 'python3'), '-m', 'art_engine', 'render',
            '--style', $style, '--seed', $seed, '--width', (string) $size, '--height', (string) $size,
        ];

        if ($animate) {
            $command[] = '--animate';
        }

        try {
            $result = Process::path((string) config('atelier.art_engine.path', base_path('python')))
                ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'])
                ->timeout(self::CLI_TIMEOUT)
                ->run($command);
        } catch (Throwable $e) {
            Log::debug('Art engine CLI could not run: '.$e->getMessage());

            return null;
        }

        if (! $result->successful()) {
            Log::debug('Art engine CLI exited with code '.$result->exitCode().': '.Str::limit(trim($result->errorOutput()), 300));

            return null;
        }

        return $this->accept($result->output(), 'CLI');
    }

    private function accept(string $svg, string $channel): ?string
    {
        if (self::looksLikeSvg($svg)) {
            return $svg;
        }

        Log::debug('Art engine '.$channel.' output rejected: not a plain SVG document.');

        return null;
    }
}
