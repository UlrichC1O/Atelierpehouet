<?php

namespace App\Cms;

use App\Support\ServiceCatalog;

/**
 * Photo spots of the pages (docs/CMS.md §4.6): the fixed ones of config('cms.slots') plus a cover
 * "service.{slug}.cover" for every service, hidden and CMS-created ones included. Which photo fills a
 * spot is stored in media_slots and read with cms()->slot($key).
 *
 * Each spot: ['page' => string, 'ratio' => string, 'service' => ?string]; "page" is an editable
 * group key for the fixed spots (home, about, community, ui…) and "service.{slug}" for covers.
 */
final class Slots
{
    private const COVER_PREFIX = 'service.';

    private const COVER_SUFFIX = '.cover';

    /**
     * @return array<string, array{page: string, ratio: string, service: string|null}>
     */
    public static function all(): array
    {
        $slots = [];

        foreach ((array) config('cms.slots', []) as $key => $slot) {
            $slots[(string) $key] = [
                'page' => (string) ($slot['page'] ?? ''),
                'ratio' => (string) ($slot['ratio'] ?? '4/3'),
                'service' => null,
            ];
        }

        $ratio = (string) config('cms.service_cover_ratio', '4/3');

        foreach (app(ServiceCatalog::class)->withHidden()->slugs() as $slug) {
            $slots[self::cover($slug)] = ['page' => self::COVER_PREFIX.$slug, 'ratio' => $ratio, 'service' => $slug];
        }

        return $slots;
    }

    /** Key of a service's cover spot. */
    public static function cover(string $slug): string
    {
        return self::COVER_PREFIX.$slug.self::COVER_SUFFIX;
    }

    public static function isValid(string $slot): bool
    {
        return array_key_exists($slot, self::all());
    }

    /**
     * Spots shown on one page (a key of the fixed spots' "page", or "service.{slug}").
     *
     * @return array<string, array{page: string, ratio: string, service: string|null}>
     */
    public static function forPage(string $page): array
    {
        return array_filter(self::all(), fn (array $slot): bool => $slot['page'] === $page);
    }

    /** CSS aspect ratio of a spot ("4/3", "1200/630"…). */
    public static function ratio(string $slot): string
    {
        return self::all()[$slot]['ratio'] ?? (string) config('cms.service_cover_ratio', '4/3');
    }

    /** Admin label: admin.slots.{key} (nested keys), admin.slots.service_cover with :service for covers. */
    public static function label(string $slot): string
    {
        if (str_starts_with($slot, self::COVER_PREFIX) && str_ends_with($slot, self::COVER_SUFFIX)) {
            $slug = substr($slot, strlen(self::COVER_PREFIX), -strlen(self::COVER_SUFFIX));
            $service = app(ServiceCatalog::class)->withHidden()->find($slug);

            return (string) __('admin.slots.service_cover', ['service' => $service['title'] ?? $slug]);
        }

        return (string) __('admin.slots.'.$slot);
    }
}
