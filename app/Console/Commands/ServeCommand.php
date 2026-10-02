<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

use function Illuminate\Support\php_binary;

/**
 * Runs the website (php artisan serve) and the Python art engine
 * (python3 -m art_engine serve) side by side, streaming both outputs.
 */
final class ServeCommand extends Command
{
    protected $signature = 'atelier:serve
        {--host=127.0.0.1 : Address the website listens on}
        {--port=8000 : Port of the website}
        {--art-port= : Port of the art engine (default: the port of ART_ENGINE_URL, else 8765)}
        {--no-art : Run the website only (artworks then come from the CLI or the built-in fallback)}';

    protected $description = 'Run the Ateliers Pehouet website and its Python art engine together';

    /** Seconds each process gets to exit gracefully before being killed. */
    private const STOP_TIMEOUT = 5;

    /** @var array<string, string> unfinished output line per process */
    private array $buffers = [];

    private bool $stopping = false;

    public function handle(): int
    {
        $host = (string) $this->option('host');
        $port = (string) $this->option('port');
        $artPort = (string) ($this->option('art-port') ?: $this->configuredArtPort());

        // A closure: the SIG* constants only exist when the pcntl extension is loaded.
        $this->trap(fn (): array => [SIGINT, SIGTERM, SIGHUP], function (): void {
            $this->stopping = true;
        });

        $this->newLine();
        $this->line('  <fg=yellow;options=bold>ATELIERS PEHOUET</> — L’art au service de la communauté');
        $this->line('  <fg=yellow>[web]</> http://'.$host.':'.$port);

        $art = $this->option('no-art') ? null : $this->startArtEngine($artPort);

        try {
            $web = Process::path(base_path())
                ->forever()
                ->start([php_binary(), 'artisan', 'serve', '--host='.$host, '--port='.$port], $this->printer('web'));
        } catch (Throwable $e) {
            $this->components->error('The website could not start: '.$e->getMessage());
            $this->stop($art);

            return self::FAILURE;
        }

        $this->line('  Press <options=bold>Ctrl+C</> to stop.');
        $this->newLine();

        while (! $this->stopping && $web->running()) {
            if ($art !== null && ! $art->running()) {
                $this->flush('art');
                $this->line('  <fg=blue>[art]</> stopped (exit code '.($art->wait()->exitCode() ?? '?').') — artworks now come from the CLI or the built-in fallback.');
                $art = null;
            }

            usleep(100_000);
        }

        $exitCode = $this->stopping ? self::SUCCESS : ($web->wait()->exitCode() ?? self::FAILURE);

        $this->stop($art);
        $this->stop($web);
        $this->flush('art');
        $this->flush('web');

        return $exitCode;
    }

    private function startArtEngine(string $port): ?InvokedProcess
    {
        $path = (string) config('atelier.art_engine.path', base_path('python'));

        if (! is_dir($path)) {
            $this->line('  <fg=blue>[art]</> skipped: no Python engine in '.$path.'.');

            return null;
        }

        try {
            $process = Process::path($path)
                ->env(['PYTHONUNBUFFERED' => '1', 'PYTHONIOENCODING' => 'utf-8'])
                ->forever()
                ->start([(string) config('atelier.art_engine.python', 'python3'), '-m', 'art_engine', 'serve', '--port', $port], $this->printer('art'));
        } catch (Throwable $e) {
            $this->line('  <fg=blue>[art]</> could not start: '.OutputFormatter::escape($e->getMessage()));

            return null;
        }

        $this->line('  <fg=blue>[art]</> http://127.0.0.1:'.$port.' (site expects '.(config('atelier.art_engine.url') ?: 'no HTTP engine').')');

        return $process;
    }

    /** Port of config('atelier.art_engine.url'), or the engine's default. */
    private function configuredArtPort(): string
    {
        $port = parse_url((string) config('atelier.art_engine.url', ''), PHP_URL_PORT);

        return is_int($port) ? (string) $port : '8765';
    }

    /**
     * Output handler printing complete lines with a coloured [name] prefix.
     *
     * @return callable(string, string): void
     */
    private function printer(string $name): callable
    {
        $this->buffers[$name] = '';

        return function (string $type, string $output) use ($name): void {
            $this->buffers[$name] .= $output;

            while (($newline = strpos($this->buffers[$name], "\n")) !== false) {
                $this->emit($name, substr($this->buffers[$name], 0, $newline));
                $this->buffers[$name] = substr($this->buffers[$name], $newline + 1);
            }
        };
    }

    private function flush(string $name): void
    {
        if (($this->buffers[$name] ?? '') !== '') {
            $this->emit($name, $this->buffers[$name]);
            $this->buffers[$name] = '';
        }
    }

    private function emit(string $name, string $line): void
    {
        $line = rtrim($line, "\r");

        if (trim($line) === '') {
            return;
        }

        $prefix = $name === 'web' ? '<fg=yellow>[web]</>' : '<fg=blue>[art]</>';
        $this->line('  '.$prefix.' '.OutputFormatter::escape($line));
    }

    private function stop(?InvokedProcess $process): void
    {
        try {
            if ($process === null || ! $process->running()) {
                return;
            }

            // InvokedProcess::stop() sends SIGTERM, then SIGKILL after the timeout.
            method_exists($process, 'stop') ? $process->stop(self::STOP_TIMEOUT) : $process->signal(15);
        } catch (Throwable) {
            // Already gone.
        }
    }
}
