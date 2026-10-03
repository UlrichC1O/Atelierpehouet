<?php

namespace App\Cms\Media;

use InvalidArgumentException;

/**
 * Resolves the storage of the photo files: the configured driver for new uploads
 * (config('cms.media.driver')), or the driver recorded on a photo to read it back.
 */
final class MediaStorageManager
{
    /** @var array<string, MediaStorage> */
    private array $drivers = [];

    /**
     * @throws InvalidArgumentException for an unknown driver
     */
    public function driver(?string $name = null): MediaStorage
    {
        $name ??= (string) config('cms.media.driver', 'database');

        return $this->drivers[$name] ??= match ($name) {
            'database' => new DatabaseMediaStorage,
            'filesystem' => new FilesystemMediaStorage((string) config('cms.media.disk', 'media')),
            default => throw new InvalidArgumentException('Unknown media driver ['.$name.'] (database|filesystem).'),
        };
    }
}
