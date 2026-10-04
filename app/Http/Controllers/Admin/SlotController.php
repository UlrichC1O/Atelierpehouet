<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Cms\SafeUrl;
use App\Cms\Slots;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/**
 * Photo spots of the pages (docs/CMS.md §7.6): which library photo fills "home.feature",
 * "service.{slug}.cover"… Posted by the slot partial (admin.media.partials.slot) — directly, from the
 * picker (media-picker.js fills media_id and submits) or from the library's "Utiliser ici" (no JS).
 *
 * A slot form carries no typed input (slot, media_id, redirect are hidden fields), so an invalid one
 * goes back with an error flash (docs/CMS.md §7.0, small forms); JSON callers get 422 {message, errors}.
 */
final class SlotController extends Controller
{
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $back = self::back($request);

        $validator = Validator::make($request->all(), [
            'slot' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Slots::isValid($value)) {
                    $fail(__('admin_media.slots.invalid'));
                }
            }],
            'media_id' => ['nullable', 'integer', 'min:1'],
        ], [], (array) __('admin_media.attributes'));

        $validator->after(function ($validator) use ($request): void {
            $id = $request->input('media_id');

            if (is_numeric($id) && (int) $id > 0 && ! Media::query()->whereKey((int) $id)->exists()) {
                $validator->errors()->add('media_id', __('admin_media.slots.missing'));
            }
        });

        if ($validator->fails()) {
            $message = (string) $validator->errors()->first();

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'errors' => $validator->errors()->toArray()], 422)
                : redirect()->to($back)->with('error', $message);
        }

        $slot = (string) $request->input('slot');
        $mediaId = filled($request->input('media_id')) ? (int) $request->input('media_id') : null;
        $current = MediaSlot::query()->where('slot', $slot)->first();
        $label = Slots::label($slot);

        if ($mediaId === null) {
            // Model deletes: FlushesCms drops the public snapshot.
            $current?->delete();
            $message = $current ? __('admin_media.slots.removed', ['slot' => $label]) : __('admin_media.slots.unchanged');
            $media = null;

            if ($current) {
                Activity::record('slots.update', __('admin_media.activity.slot_cleared', ['slot' => $label]), 'slot:'.$slot,
                    before: ['media_id' => $current->media_id], after: ['media_id' => null]);
            }
        } else {
            $before = $current?->media_id;
            $slotRow = $current ?? new MediaSlot(['slot' => $slot]);
            $slotRow->forceFill(['media_id' => $mediaId, 'updated_by' => $request->user()?->getKey()])->save();
            $media = Media::query()->find($mediaId);
            $message = __('admin_media.slots.assigned', ['slot' => $label]);

            if ($before !== $mediaId) {
                Activity::record('slots.update', __('admin_media.activity.slot_set', ['slot' => $label, 'name' => MediaController::displayName($media)]), 'slot:'.$slot,
                    before: ['media_id' => $before], after: ['media_id' => $mediaId]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'slot' => $slot,
                'media' => $media ? $media->item()->toArray() : null,
                'view_url' => $media ? self::pageUrl($slot) : null,
            ]);
        }

        return redirect()->to($back)->with('status', $message);
    }

    /**
     * The public page showing a photo spot, at its #spot-{slot-with-dashes} anchor (docs/CMS.md §13
     * F31), or null (the share image is shown on no page).
     */
    public static function pageUrl(string $slot): ?string
    {
        $anchor = '#spot-'.str_replace('.', '-', $slot);

        if (preg_match('/^service\.([a-z0-9-]+)\.cover$/', $slot, $match) === 1) {
            return Route::has('services.show') ? route('services.show', $match[1]).$anchor : null;
        }

        if ($slot === 'site.share') {
            return null;
        }

        $page = ((array) config('cms.slots', []))[$slot]['page'] ?? null;
        $route = is_string($page) ? (((array) config('cms.editable_groups', []))[$page] ?? null) : null;

        return is_string($route) && Route::has($route) ? route($route).$anchor : null;
    }

    /** The admin screen where a spot is usually changed (its service, or the texts of its page). */
    public static function adminUrl(string $slot): ?string
    {
        if (preg_match('/^service\.([a-z0-9-]+)\.cover$/', $slot, $match) === 1) {
            return Route::has('admin.services.edit') ? route('admin.services.edit', $match[1]) : null;
        }

        $page = ((array) config('cms.slots', []))[$slot]['page'] ?? null;

        return is_string($page) && array_key_exists($page, (array) config('cms.editable_groups', [])) && Route::has('admin.texts.edit')
            ? route('admin.texts.edit', $page)
            : null;
    }

    /** Where to go afterwards: the form's "redirect" (this site only), else the previous admin page. */
    private static function back(Request $request): string
    {
        $fallback = SafeUrl::internal(url()->previous(route('admin.media.index')), route('admin.media.index'));
        $redirect = $request->input('redirect');

        return SafeUrl::internal(is_string($redirect) ? $redirect : null, $fallback);
    }
}
