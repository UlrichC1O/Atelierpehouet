<?php

namespace App\Cms\Media;

/**
 * Where the files of the photo library live (docs/CMS.md §4.5). Keys are {ulid}.{ext} or
 * {ulid}-{width}.{ext}; implementations refuse any other key (no path can be smuggled in).
 */
interface MediaStorage
{
    /** Stores a file; throws on failure. */
    public function put(string $key, string $bytes, string $mime): void;

    /**
     * A stored file, or null when it does not exist.
     *
     * @return array{bytes: string, mime: string, size: int, modified: int|null}|null
     */
    public function get(string $key): ?array;

    /** Deletes a file (a missing file is not an error). */
    public function delete(string $key): void;

    public function exists(string $key): bool;

    /** Name stored in media.driver: "database" or "filesystem". */
    public function driver(): string;
}
