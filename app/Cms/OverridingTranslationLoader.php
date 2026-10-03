<?php

namespace App\Cms;

use Closure;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translation loader that lays the texts edited in the CMS over the lang/ files (docs/CMS.md §4.3).
 *
 * Registered by AppServiceProvider with $app->extend('translation.loader', …): the translation
 * provider is deferred, so the extender is stored and applied when the translator first resolves
 * its loader. Only groups of Cms::editableGroups() are overlaid, and an override replaces a line
 * only when its key exists in the file as a string leaf: stale keys (the file changed since) are
 * ignored and the structure of the files (lists, placeholders' arrays) can never change.
 */
final class OverridingTranslationLoader implements Loader
{
    /**
     * @param  Closure(): Cms  $cms  resolved lazily: only when an editable group is loaded
     */
    public function __construct(private readonly Loader $files, private readonly Closure $cms) {}

    /** The wrapped file loader: the defaults, without any override (the admin reads them through it). */
    public function files(): Loader
    {
        return $this->files;
    }

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<array-key, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->files->load($locale, $group, $namespace);

        if (($namespace !== null && $namespace !== '*') || $lines === [] || ! array_key_exists($group, Cms::editableGroups())) {
            return $lines;
        }

        try {
            foreach (($this->cms)()->translations($locale, $group) as $key => $value) {
                $key = (string) $key;

                if (is_string($value) && Arr::has($lines, $key) && is_string(Arr::get($lines, $key))) {
                    Arr::set($lines, $key, $value);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Text overrides of '.$locale.'/'.$group.' ignored: '.$e->getMessage());
        }

        return $lines;
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    /**
     * @param  string  $path
     * @return void
     */
    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces()
    {
        return $this->files->namespaces();
    }

    /**
     * FileLoader methods outside the contract (addPath(), paths(), jsonPaths()…), used by the translator.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->files->{$method}(...$arguments);
    }
}
