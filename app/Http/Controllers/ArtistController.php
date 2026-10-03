<?php

namespace App\Http\Controllers;

use App\Artists\ArtistDirectory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The public artist pages (docs/ARTISTS.md §5 and §12): /artistes and /artistes/{slug}.
 *
 * Read through App\Artists\ArtistDirectory, which never throws and serves its last good copy while the
 * database is unreachable. Only when there is no artist data at all (database down and nothing cached,
 * or the tables not migrated yet) do the pages answer 503 + Retry-After — the index with its calm
 * "unavailable" state — so that search engines come back later instead of indexing an empty page.
 * An unknown slug, while the data is available, is a 404. An unpublished artist is shown only to a
 * logged-in administrator, as a preview.
 */
final class ArtistController extends Controller
{
    /** Other published artists suggested at the bottom of an artist page. */
    private const OTHERS = 3;

    /** Seconds after which a visitor or crawler should try an unavailable page again. */
    private const RETRY_AFTER = 300;

    public function __construct(private readonly ArtistDirectory $directory) {}

    public function index(): Response
    {
        $available = $this->directory->available();
        $response = response()->view('artists.index', [
            'artists' => $this->directory->all(),
            'available' => $available,
        ], $available ? 200 : 503);

        return $available ? $response : self::unavailable($response);
    }

    public function show(Request $request, string $slug): Response
    {
        $artist = $this->directory->find($slug, null, ArtistDirectory::canPreview($request));

        if ($artist === null) {
            abort_unless($this->directory->available(), 503, '', self::unavailableHeaders());
            abort(404);
        }

        $neighbors = $artist['published'] ? $this->directory->neighbors($slug) : ['prev' => null, 'next' => null];

        // With two artists the previous and the next one are the same page: link it once.
        if ($neighbors['prev'] !== null && $neighbors['prev']['slug'] === ($neighbors['next']['slug'] ?? null)) {
            $neighbors['prev'] = null;
        }

        return response()->view('artists.show', [
            'artist' => $artist,
            'preview' => ! $artist['published'],
            'prev' => $neighbors['prev'],
            'next' => $neighbors['next'],
            'others' => $this->others($slug),
        ]);
    }

    /**
     * Up to three other published artists: the ones following this artist in the list order (wrapping
     * around), so each page suggests different neighbours.
     *
     * @return list<array<string, mixed>>
     */
    private function others(string $slug): array
    {
        $artists = $this->directory->all();
        $index = array_search($slug, array_column($artists, 'slug'), true);

        if (is_int($index)) {
            $artists = array_merge(array_slice($artists, $index + 1), array_slice($artists, 0, $index));
        } else {
            $artists = array_values(array_filter($artists, fn (array $artist): bool => $artist['slug'] !== $slug));
        }

        return array_slice($artists, 0, self::OTHERS);
    }

    private static function unavailable(Response $response): Response
    {
        return $response->withHeaders(self::unavailableHeaders());
    }

    /**
     * @return array<string, string>
     */
    private static function unavailableHeaders(): array
    {
        return ['Retry-After' => (string) self::RETRY_AFTER, 'Cache-Control' => 'no-store'];
    }
}
