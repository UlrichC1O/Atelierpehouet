<?php

namespace Tests\Unit\Cms;

use App\Cms\MediaItem;
use Tests\TestCase;

/** The photo value object the pages use (docs/CMS.md §4.2). */
class MediaItemTest extends TestCase
{
    private const ULID = '01j9z8x7w6v5t4s3r2q1p0n9m8';

    private function item(array $overrides = []): MediaItem
    {
        return MediaItem::fromArray($overrides + [
            'id' => 7,
            'ulid' => strtoupper(self::ULID),
            'extension' => 'webp',
            'mime' => 'image/webp',
            'width' => 1600,
            'height' => 1200,
            'size' => 52000,
            'variants' => json_encode([
                ['width' => 960, 'height' => 720, 'size' => 21000, 'key' => self::ULID.'-960.webp'],
                ['width' => 480, 'height' => 360, 'size' => 9000, 'key' => self::ULID.'-480.webp'],
                ['width' => 120, 'height' => 90, 'size' => 10, 'key' => '../../etc/passwd'],
            ]),
            'alt_fr' => '  Fresque de l’école  ',
            'alt_en' => '',
            'caption_fr' => 'Une fresque participative',
            'caption_en' => null,
            'focal_x' => 130,
            'focal_y' => '30',
            'service_slug' => 'peinture-murale',
            'in_gallery' => 1,
            'position' => 2,
            'original_name' => 'IMG_2041.jpg',
            'updated_at' => '2026-10-03 10:00:00',
        ]);
    }

    public function test_it_is_built_from_a_database_row(): void
    {
        $item = $this->item();

        $this->assertSame(self::ULID, $item->ulid);
        $this->assertSame([480, 960], array_column($item->variants, 'width'), 'sorted, unsafe keys dropped');
        $this->assertSame(['fr' => 'Fresque de l’école', 'en' => null], $item->alt);
        $this->assertSame(100, $item->focalX);
        $this->assertSame(30, $item->focalY);
        $this->assertSame('peinture-murale', $item->service);
        $this->assertTrue($item->inGallery);
        $this->assertSame('2026-10-03 10:00:00', $item->updatedAt);
    }

    public function test_keys_and_urls_pick_the_smallest_wide_enough_file(): void
    {
        $item = $this->item();

        $this->assertSame(self::ULID.'.webp', $item->key());
        $this->assertSame(self::ULID.'-480.webp', $item->key(300));
        $this->assertSame(self::ULID.'-480.webp', $item->key(480));
        $this->assertSame(self::ULID.'-960.webp', $item->key(500));
        $this->assertSame(self::ULID.'.webp', $item->key(1200));
        $this->assertSame(route('media.show', self::ULID.'-960.webp'), $item->url(960));
        $this->assertSame(url('/media/'.self::ULID.'.webp'), $item->url());
        $this->assertSame(
            url('/media/'.self::ULID.'-480.webp').' 480w, '.url('/media/'.self::ULID.'-960.webp').' 960w, '.url('/media/'.self::ULID.'.webp').' 1600w',
            $item->srcset(),
        );
    }

    public function test_texts_fall_back_to_french_then_to_the_caption(): void
    {
        $item = $this->item();

        $this->assertSame('Fresque de l’école', $item->alt('en'));
        $this->assertSame('Une fresque participative', $item->caption('en'));
        $this->assertSame('Une fresque participative', $this->item(['alt_fr' => null])->alt('fr'));
        $this->assertSame('', $this->item(['alt_fr' => null, 'caption_fr' => ''])->alt());
        $this->assertNull($this->item(['caption_fr' => null])->caption());
        $this->assertSame('English alt', $this->item(['alt_en' => 'English alt'])->alt('en'));
    }

    public function test_layout_helpers(): void
    {
        $item = $this->item();

        $this->assertSame('1600 / 1200', $item->ratio());
        $this->assertSame('100% 30%', $item->objectPosition());
        $this->assertTrue($item->isLandscape());
        $this->assertFalse($this->item(['width' => 800, 'height' => 800])->isLandscape());
    }

    public function test_to_array_is_json_safe_and_round_trips(): void
    {
        $item = $this->item();
        $array = $item->toArray();

        $this->assertSame(json_decode(json_encode($array), true), $array);
        $this->assertSame($item->url(), $array['url']);
        $this->assertSame($item->url(480), $array['thumb']);
        $this->assertSame($item->srcset(), $array['srcset']);
        $this->assertEquals($item, MediaItem::fromArray($array));
        $this->assertEquals($item, MediaItem::fromArray($item->toSnapshot()));
        $this->assertArrayNotHasKey('url', $item->toSnapshot(), 'the cached snapshot never holds host-dependent URLs');
    }
}
