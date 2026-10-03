#!/usr/bin/env php
<?php

use Illuminate\Contracts\Console\Kernel;

/*
 * Compile Blade templates and syntax-check the generated PHP.
 *   php bin/blade-lint.php resources/views/pages/home.blade.php [more files or directories]
 * Exit code 1 when any template fails to compile or lint.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$targets = array_slice($argv, 1) ?: [resource_path('views')];
$files = [];
foreach ($targets as $target) {
    if (is_dir($target)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }
    } else {
        $files[] = $target;
    }
}
sort($files);

$compiler = app('blade.compiler');
$failed = 0;
foreach ($files as $file) {
    try {
        $php = $compiler->compileString(file_get_contents($file));
    } catch (Throwable $e) {
        echo "✗ {$file}: compile error: {$e->getMessage()}\n";
        $failed++;

        continue;
    }
    $base = tempnam(sys_get_temp_dir(), 'blade');
    $tmp = $base.'.php';
    file_put_contents($tmp, $php);
    $out = [];
    exec('php -l '.escapeshellarg($tmp).' 2>&1', $out, $code);
    unlink($tmp);
    unlink($base);
    if ($code !== 0) {
        echo "✗ {$file}: ".preg_replace('/in \S+ on/', 'on', trim(implode(' ', array_filter($out, fn ($l) => ! str_starts_with($l, 'Errors parsing')))))."\n";
        $failed++;
    } else {
        echo "✓ {$file}\n";
    }
    $out = [];
}
echo $failed ? "{$failed} template(s) failed.\n" : count($files)." template(s) OK.\n";
exit($failed ? 1 : 0);
