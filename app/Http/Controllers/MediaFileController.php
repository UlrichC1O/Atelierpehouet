<?php

namespace App\Http\Controllers;

use App\Cms\DatabaseHealth;
use App\Cms\Media\MediaStorageManager;
use App\Cms\Media\RetiredFiles;
use App\Cms\MediaItem;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * GET /media/{key} — the photos of the CMS library (docs/CMS.md §4.5, §13 C12).
 *
 * Stateless (no session, no cookie). A key never changes content (replacing a photo gives it a new
 * ulid), so browsers keep a file for a year (immutable) and revalidate it with ETag = the key, while
 * Vercel's CDN keeps its copy for a day only, so a deleted photo leaves the edge within 24 hours.
 * The key of a photo replaced or deleted a moment ago is still served for RetiredFiles::grace()
 * seconds: pages cached with the old URLs keep working until their snapshot expires.
 *
 * The file is read through the storage driver recorded on the photo and served with nosniff and a
 * CSP that forbids everything, so even a hostile file could not run as a document. While the
 * database is known to be unreachable (circuit breaker), the answer is an immediate 503.
 */
final class MediaFileController extends Controller
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    private const CDN_CACHE_CONTROL = 'public, max-age=86400';

    public function __invoke(Request $request, MediaStorageManager $storages, RetiredFiles $retired, DatabaseHealth $health, string $key): Response
    {
        if (preg_match(MediaItem::KEY_PATTERN, $key, $matches) !== 1) {
            return $this->missing();
        }

        if (! $health->available()) {
            return $this->unavailable(null);
        }

        try {
            $media = Media::query()->where('ulid', $matches[1])->first(['id', 'ulid', 'extension', 'driver', 'variants']);
        } catch (Throwable $e) {
            $health->failed($e);

            return $this->unavailable($e);
        }

        $driver = $media !== null && in_array($key, $media->keys(), true)
            ? $media->driver
            : rescue(fn () => $retired->driverOf($key), null, false); // a missing table (migration pending) ⇒ unknown

        if (! is_string($driver)) {
            return $this->missing();
        }

        $response = new Response('', Response::HTTP_OK, [
            'Cache-Control' => self::CACHE_CONTROL,
            'Vercel-CDN-Cache-Control' => self::CDN_CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
        $response->setEtag($key);

        // A browser that already has this key has the right bytes: no need to read them.
        if ($response->isNotModified($request)) {
            return $response;
        }

        try {
            $file = $storages->driver($driver)->get($key);
        } catch (Throwable $e) {
            if ($driver === 'database') {
                $health->failed($e); // a disk error says nothing about the database
            }

            return $this->unavailable($e);
        }

        if ($file === null) {
            return $this->missing();
        }

        $response->setContent($file['bytes']);
        $response->headers->add([
            'Content-Type' => $file['mime'],
            'Content-Length' => (string) strlen($file['bytes']),
            'Content-Disposition' => 'inline; filename="pehouet-'.$key.'"',
        ]);

        return $response;
    }

    /** A plain 404 (no HTML page for an image URL); never cached, the key may appear later. */
    private function missing(): Response
    {
        return new Response('Not Found', Response::HTTP_NOT_FOUND, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The database or the storage is down: a short, uncached 503 so the image is retried. */
    private function unavailable(?Throwable $e): Response
    {
        if ($e !== null) {
            Log::warning('Photo could not be served: '.$e->getMessage());
        }

        return new Response('Service Unavailable', Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => '30',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
