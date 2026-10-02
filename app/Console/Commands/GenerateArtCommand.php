<?php

namespace App\Console\Commands;

use App\Support\ServiceCatalog;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pre-renders the generative artworks with the Python engine's CLI
 * (docs/ARCHITECTURE.md §11) into public/generated:
 *   gallery mode  → gallery/{style}-{n}.svg + gallery/manifest.json
 *   batch mode    → services/{slug}-{1..3}.svg (seed "{slug}-{n}", the service's art style)
 */
final class GenerateArtCommand extends Command
{
    protected $signature = 'atelier:generate-art
        {--only= : Render only "gallery" or "services"}
        {--per-style=4 : Gallery artworks per style}
        {--size=800 : Width and height of the gallery artworks, in pixels}';

    protected $description = 'Render the gallery and the service artworks with the Python art engine';

    /** Artworks per service page. */
    public const PER_SERVICE = 3;

    public const SERVICE_SIZE = 800;

    /** Seconds the engine may take for one batch. */
    private const TIMEOUT = 900;

    public function handle(ServiceCatalog $catalog): int
    {
        $only = $this->option('only');
        $perStyle = filter_var($this->option('per-style'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $size = filter_var($this->option('size'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 64, 'max_range' => 2400]]);

        if ($only !== null && ! in_array($only, ['gallery', 'services'], true)) {
            $this->components->error('--only must be "gallery" or "services".');

            return self::INVALID;
        }

        if ($perStyle === false || $size === false) {
            $this->components->error('--per-style must be between 1 and 50, --size between 64 and 2400.');

            return self::INVALID;
        }

        $engine = (string) config('atelier.art_engine.path', base_path('python'));

        if (! is_dir($engine)) {
            $this->components->error('Python art engine not found in '.$engine.'.');

            return self::FAILURE;
        }

        $out = public_path('generated');
        File::ensureDirectoryExists($out);

        $ok = true;

        if ($only !== 'services') {
            $ok = $this->gallery($out, $perStyle, $size) && $ok;
        }

        if ($only !== 'gallery') {
            $ok = $this->services($catalog, $out) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function gallery(string $out, int $perStyle, int $size): bool
    {
        $this->components->info('Gallery: '.$perStyle.' artwork(s) per style, '.$size.'×'.$size.' px…');

        $result = $this->python(['gallery', '--out', $out, '--per-style', (string) $perStyle, '--size', (string) $size]);

        if ($result === null || ! $result->successful()) {
            return $this->failed('gallery', $result);
        }

        $written = $this->written($result) ?? $this->manifestCount($out.'/gallery/manifest.json');
        $this->components->twoColumnDetail('Gallery', ($written ?? '?').' SVG → '.$this->relative($out.'/gallery'));

        return true;
    }

    private function services(ServiceCatalog $catalog, string $out): bool
    {
        $items = [];

        foreach ($catalog->all() as $service) {
            for ($n = 1; $n <= self::PER_SERVICE; $n++) {
                $items[] = [
                    'file' => 'services/'.$service['slug'].'-'.$n.'.svg',
                    'style' => $service['art_style'],
                    'seed' => $service['slug'].'-'.$n,
                    'width' => self::SERVICE_SIZE,
                    'height' => self::SERVICE_SIZE,
                    'animate' => false,
                ];
            }
        }

        if ($items === []) {
            $this->components->warn('Services: no service content in '.$this->relative($catalog->directory()).', nothing to render.');

            return true;
        }

        $this->components->info('Services: '.count($items).' artworks for '.$catalog->count().' services…');

        $spec = tempnam(sys_get_temp_dir(), 'pehouet-spec-');

        if ($spec === false || file_put_contents($spec, json_encode(['items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            $this->components->error('Could not write the batch spec to the temporary directory.');

            return false;
        }

        try {
            $result = $this->python(['batch', '--spec', $spec, '--out', $out]);
        } finally {
            @unlink($spec);
        }

        if ($result === null || ! $result->successful()) {
            return $this->failed('services', $result);
        }

        $this->components->twoColumnDetail('Services', ($this->written($result) ?? count($items)).' SVG → '.$this->relative($out.'/services'));

        return true;
    }

    /**
     * Runs `python -m art_engine …` in the engine directory (null when it cannot start).
     *
     * @param  list<string>  $arguments
     */
    private function python(array $arguments): ?ProcessResult
    {
        try {
            return Process::path((string) config('atelier.art_engine.path', base_path('python')))
                ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'])
                ->timeout(self::TIMEOUT)
                ->run([(string) config('atelier.art_engine.python', 'python3'), '-m', 'art_engine', ...$arguments]);
        } catch (Throwable $e) {
            $this->components->error('The art engine could not run: '.$e->getMessage());

            return null;
        }
    }

    private function failed(string $mode, ?ProcessResult $result): bool
    {
        if ($result !== null) {
            $details = trim($result->errorOutput()) ?: trim($result->output());
            $this->components->error(ucfirst($mode).' failed (exit code '.$result->exitCode().').');

            if ($details !== '') {
                $this->line(Str::limit($details, 2000));
            }
        }

        return false;
    }

    /** "written" count printed by the engine as JSON, if any. */
    private function written(ProcessResult $result): ?int
    {
        $summary = json_decode(trim($result->output()), true);

        return is_array($summary) && is_int($summary['written'] ?? null) ? $summary['written'] : null;
    }

    private function manifestCount(string $manifest): ?int
    {
        $data = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;

        return is_array($data['items'] ?? null) ? count($data['items']) : null;
    }

    private function relative(string $path): string
    {
        return ltrim(Str::after($path, base_path()), '/');
    }
}
