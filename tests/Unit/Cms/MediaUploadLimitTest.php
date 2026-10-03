<?php

namespace Tests\Unit\Cms;

use App\Cms\Media\MediaManager;
use Tests\TestCase;

/** The largest upload the admin accepts (docs/CMS.md §13 C10): the smallest of every limit on the way. */
class MediaUploadLimitTest extends TestCase
{
    private const MB = 1024 * 1024;

    private const FORM_OVERHEAD = 256 * 1024;

    public function test_the_smallest_limit_wins(): void
    {
        $this->assertSame(5 * self::MB, MediaManager::uploadLimit(8192 * 1024, '5M', '6M'));
        $this->assertSame(6 * self::MB - self::FORM_OVERHEAD, MediaManager::uploadLimit(8192 * 1024, '10M', '6M'), 'room is left for the other fields');
        $this->assertSame(2 * self::MB, MediaManager::uploadLimit(2048 * 1024, '10M', '12M'));
        $this->assertSame(4_000_000, MediaManager::uploadLimit(8192 * 1024, '5M', '6M', 4_000_000), 'Vercel');
        $this->assertSame(1536 * 1024, MediaManager::uploadLimit(8192 * 1024, '1536K', '1G'));
        $this->assertSame(1_000_000, MediaManager::uploadLimit(8192 * 1024, '1000000', '8M'), 'plain bytes');
    }

    public function test_unset_or_unreadable_php_limits_are_ignored(): void
    {
        $this->assertSame(8192 * 1024, MediaManager::uploadLimit(8192 * 1024, '0', '0'), '0 means no limit in php.ini');
        $this->assertSame(8192 * 1024, MediaManager::uploadLimit(8192 * 1024, '', ' '));
        $this->assertSame(8192 * 1024, MediaManager::uploadLimit(8192 * 1024, 'abc', '-'));
        $this->assertSame(1, MediaManager::uploadLimit(8192 * 1024, '5M', '100K'), 'never zero or negative');
    }

    public function test_the_vercel_runtime_settings_let_vercels_bodies_through(): void
    {
        $ini = parse_ini_file(base_path('api/php.ini'), false, INI_SCANNER_RAW);

        $this->assertIsArray($ini);
        $this->assertSame(['5M', '6M'], [$ini['upload_max_filesize'] ?? null, $ini['post_max_size'] ?? null]);
        $this->assertSame(4_000_000, MediaManager::uploadLimit(8192 * 1024, $ini['upload_max_filesize'], $ini['post_max_size'], 4_000_000));
    }

    public function test_the_manager_reads_the_configuration_and_php_ini(): void
    {
        $ini = [(string) ini_get('upload_max_filesize'), (string) ini_get('post_max_size')];
        $manager = app(MediaManager::class);

        config(['cms.media.max_kb' => 8192, 'cms.media.host_max_bytes' => null]);
        $this->assertSame(MediaManager::uploadLimit(8192 * 1024, ...$ini), $manager->maxUploadBytes());

        config(['cms.media.host_max_bytes' => 4_000_000]);
        $this->assertSame(MediaManager::uploadLimit(8192 * 1024, $ini[0], $ini[1], 4_000_000), $manager->maxUploadBytes());
        $this->assertLessThanOrEqual(4_000_000, $manager->maxUploadBytes());

        config(['cms.media.max_kb' => 100]);
        $this->assertSame(100 * 1024, $manager->maxUploadBytes());
    }
}
