<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Cms\SafeUrl;
use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The public gallery's photos and their order (docs/CMS.md §7.6): a sortable grid (positions saved
 * by sortable.js as JSON, or by the form without JavaScript — including its "move up / down"
 * buttons), "remove from the gallery" switches and an uploader preset with in_gallery = 1.
 *
 * Order on the site: position ascending, then newest first. A reorder numbers the photos 1…n, so a
 * new photo (position 0) shows first until the next reorder.
 */
final class GalleryController extends Controller
{
    public function index(): Response
    {
        $photos = self::photos();
        $services = MediaController::serviceOptions();

        return response()->view('admin.gallery.index', [
            'photos' => array_map(fn (Media $media): array => [
                'media' => $media,
                'item' => $media->item(),
                'name' => MediaController::displayName($media),
                'service' => is_string($media->service_slug) && $media->service_slug !== '' ? ($services[$media->service_slug] ?? $media->service_slug) : null,
            ], $photos),
        ]);
    }

    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $requested = array_values(array_filter(
            array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, (array) $request->input('order', [])),
            fn (int $id): bool => $id > 0,
        ));

        $current = array_map(fn (Media $media): int => (int) $media->getKey(), self::photos());
        // The requested order first (gallery photos only), then any gallery photo it did not list
        // (added in another tab meanwhile), in their current order.
        $order = array_values(array_unique(array_merge(array_values(array_intersect($requested, $current)), $current)));
        $order = self::applyMove($order, $request->input('move'));

        DB::transaction(function () use ($order): void {
            foreach ($order as $index => $id) {
                Media::query()->whereKey($id)->where('position', '!=', $index + 1)->update(['position' => $index + 1]);
            }

            DB::afterCommit(fn () => cms()->flush());
        });

        Activity::record('gallery.reorder', __('admin_media.activity.gallery_reordered'), 'gallery', after: ['order' => $order]);

        $message = __('admin_media.gallery.reordered');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'order' => $order]);
        }

        return redirect()->route('admin.gallery.index')->with('status', $message);
    }

    /** Adds a photo to the gallery or takes it out (in_gallery = 0|1); the photo stays in the library. */
    public function toggle(Request $request, Media $media): JsonResponse|RedirectResponse
    {
        $show = $request->boolean('in_gallery');
        $before = MediaController::metadata($media);
        $media->forceFill(['in_gallery' => $show]);

        if ($media->isDirty('in_gallery')) {
            $media->save();
            Activity::record($show ? 'gallery.add' : 'gallery.remove',
                __($show ? 'admin_media.activity.gallery_added' : 'admin_media.activity.gallery_removed', ['name' => MediaController::displayName($media)]),
                'media:'.$media->getKey(), before: $before, after: MediaController::metadata($media));
        }

        $message = __($show ? 'admin_media.gallery.added' : 'admin_media.gallery.removed');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'in_gallery' => $show]);
        }

        $redirect = $request->input('redirect');

        return redirect()->to(SafeUrl::internal(is_string($redirect) ? $redirect : null, route('admin.gallery.index')))->with('status', $message);
    }

    /**
     * The gallery's photos in the site's order.
     *
     * @return list<Media>
     */
    private static function photos(): array
    {
        return Media::query()->where('in_gallery', true)->orderBy('position')->orderByDesc('id')->get()->all();
    }

    /**
     * A "move up / down" button of the form without JavaScript: "{id}:up" or "{id}:down".
     *
     * @param  list<int>  $order
     * @return list<int>
     */
    private static function applyMove(array $order, mixed $move): array
    {
        if (! is_string($move) || preg_match('/^(\d+):(up|down)$/', $move, $match) !== 1) {
            return $order;
        }

        $index = array_search((int) $match[1], $order, true);
        $target = $index === false ? false : ($match[2] === 'up' ? $index - 1 : $index + 1);

        if ($index === false || $target < 0 || $target >= count($order)) {
            return $order;
        }

        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        return $order;
    }
}
