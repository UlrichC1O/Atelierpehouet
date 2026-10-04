<?php

namespace App\Http\Controllers\Admin;

use App\Artists\MediaUsage;
use App\Cms\Activity;
use App\Cms\Media\InvalidImage;
use App\Cms\Media\MediaManager;
use App\Cms\SafeUrl;
use App\Cms\Slots;
use App\Cms\Text;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Models\Media;
use App\Models\MediaSlot;
use App\Models\User;
use App\Support\ServiceCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The photo library (docs/CMS.md §7.6, §13 C10–C14): list with filters and search (also as the
 * picker's partial, ?picker=1, and as the no-JS slot chooser, ?slot=…&redirect=…), upload, edit
 * (texts, focal point, placement), replace and delete.
 *
 * Files go through App\Cms\Media\MediaManager only. Uploads answer JSON for the uploader
 * (201 {media, message, where, view_url} / 422 {message, errors}) or redirect with a flash.
 */
final class MediaController extends Controller
{
    use RendersInvalidForms;

    /** Photos per page of the library and of the picker (docs/CMS.md §13 C13). */
    public const PER_PAGE = 48;

    /** Destinations of the library uploader (docs/CMS.md §13 C14). */
    public const DESTINATIONS = ['gallery', 'service', 'library'];

    /** Fields searched by the library's search box. */
    private const SEARCHED = ['original_name', 'alt_fr', 'alt_en', 'caption_fr', 'caption_en'];

    /** MIME types of the iPhone's HEIC/HEIF photos (refused with their own help message). */
    private const HEIC = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

    public function __construct(private readonly MediaManager $manager) {}

    public function index(Request $request): Response
    {
        $data = $this->libraryData($request);

        return response()->view($data['picker'] ? 'admin.media.picker' : 'admin.media.index', $data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $back = self::redirectInput($request, route('admin.media.index'));
        $services = array_keys(self::serviceOptions());

        $validator = Validator::make($request->except(['photo', 'variants']), [
            'alt_fr' => ['nullable', 'string', 'max:300'],
            'alt_en' => ['nullable', 'string', 'max:300'],
            'caption_fr' => ['nullable', 'string', 'max:500'],
            'caption_en' => ['nullable', 'string', 'max:500'],
            'destination' => ['nullable', Rule::in(self::DESTINATIONS)],
            'service_slug' => ['nullable', 'string', Rule::in($services), Rule::requiredIf($request->input('destination') === 'service')],
            'in_gallery' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'original_name' => ['nullable', 'string', 'max:255'],
            'slot' => ['nullable', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || ! Slots::isValid($value)) {
                    $fail(__('admin_media.slots.invalid'));
                }
            }],
        ], ['service_slug.required' => __('admin_media.errors.service')], (array) __('admin_media.attributes'));

        if ($validator->fails()) {
            return $this->uploadFailed($request, (string) $validator->errors()->first(), $back, $validator->errors()->toArray());
        }

        $photo = $request->file('photo');

        if (! $photo instanceof UploadedFile) {
            return $this->uploadFailed($request, __('admin_media.errors.missing'), $back);
        }

        [$inGallery, $service] = self::destination($request);
        $attributes = array_filter([
            'alt_fr' => $request->input('alt_fr'),
            'alt_en' => $request->input('alt_en'),
            'caption_fr' => $request->input('caption_fr'),
            'caption_en' => $request->input('caption_en'),
            'position' => $request->input('position'),
            'original_name' => $request->input('original_name'),
        ], fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '') + ['in_gallery' => $inGallery, 'service_slug' => $service];

        try {
            $media = $this->manager->store($photo, self::variants($request), $attributes, $request->user()?->getKey());
        } catch (InvalidImage $e) {
            return $this->refused($request, $e, $photo, $back);
        }

        $slot = $request->filled('slot') ? (string) $request->input('slot') : null;

        if ($slot !== null) {
            $row = MediaSlot::query()->where('slot', $slot)->first() ?? new MediaSlot(['slot' => $slot]);
            $row->forceFill(['media_id' => $media->getKey(), 'updated_by' => $request->user()?->getKey()])->save();
        }

        Activity::record('media.upload', __('admin_media.activity.uploaded', ['name' => self::displayName($media)]), 'media:'.$media->getKey(), after: self::metadata($media));

        [$where, $message, $viewUrl] = $this->placement($media, $slot);

        if ($request->expectsJson()) {
            return response()->json([
                'media' => self::json($media),
                'message' => $message,
                'where' => $where,
                'service' => $media->service_slug ? self::serviceTitle($media->service_slug) : null,
                'view_url' => $viewUrl,
            ], 201);
        }

        return redirect()->to($back)->with('status', $message)->with('media_view_url', $viewUrl);
    }

    public function edit(Media $media): Response
    {
        return response()->view('admin.media.edit', $this->editData($media));
    }

    public function update(Request $request, Media $media): Response|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'alt_fr' => ['nullable', 'string', 'max:300'],
            'alt_en' => ['nullable', 'string', 'max:300'],
            'caption_fr' => ['nullable', 'string', 'max:500'],
            'caption_en' => ['nullable', 'string', 'max:500'],
            'service_slug' => ['nullable', 'string', Rule::in(array_keys(self::serviceOptions()))],
            'in_gallery' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'focal_x' => ['required', 'integer', 'between:0,100'],
            'focal_y' => ['required', 'integer', 'between:0,100'],
            'original_name' => ['nullable', 'string', 'max:255'],
        ], [], (array) __('admin_media.attributes'));

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.media.edit', $this->editData($media), $validator);
        }

        $before = self::metadata($media);

        $media->forceFill([
            'alt_fr' => self::text($request->input('alt_fr'), 300),
            'alt_en' => self::text($request->input('alt_en'), 300),
            'caption_fr' => self::text($request->input('caption_fr'), 500),
            'caption_en' => self::text($request->input('caption_en'), 500),
            'service_slug' => $request->filled('service_slug') ? (string) $request->input('service_slug') : null,
            'in_gallery' => $request->boolean('in_gallery'),
            'position' => (int) $request->input('position', 0),
            'focal_x' => (int) $request->input('focal_x'),
            'focal_y' => (int) $request->input('focal_y'),
            'original_name' => self::text(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', str_replace(['/', '\\'], '-', (string) $request->input('original_name', ''))) ?? '', 255),
        ]);

        if (! $media->isDirty()) {
            return redirect()->route('admin.media.edit', $media)->with('info', __('admin_media.flash.unchanged'));
        }

        $media->save();

        Activity::record('media.update', __('admin_media.activity.updated', ['name' => self::displayName($media)]), 'media:'.$media->getKey(),
            before: $before, after: self::metadata($media));

        return redirect()->route('admin.media.edit', $media)->with('status', __('admin_media.flash.updated'));
    }

    public function replace(Request $request, Media $media): JsonResponse|RedirectResponse
    {
        $back = self::redirectInput($request, route('admin.media.edit', $media));
        $photo = $request->file('photo');

        if (! $photo instanceof UploadedFile) {
            return $this->uploadFailed($request, __('admin_media.errors.missing'), $back);
        }

        $name = $request->input('original_name');

        try {
            $this->manager->replace($media, $photo, self::variants($request), is_string($name) ? $name : null);
        } catch (InvalidImage $e) {
            return $this->refused($request, $e, $photo, $back);
        }

        Activity::record('media.replace', __('admin_media.activity.replaced', ['name' => self::displayName($media)]), 'media:'.$media->getKey());

        $message = __('admin_media.flash.replaced');

        if ($request->expectsJson()) {
            return response()->json(['media' => self::json($media), 'message' => $message]);
        }

        return redirect()->route('admin.media.edit', $media)->with('status', $message);
    }

    public function destroy(Request $request, Media $media): JsonResponse|RedirectResponse
    {
        $name = self::displayName($media);
        $before = self::metadata($media);

        $this->manager->delete($media);

        Activity::record('media.delete', __('admin_media.activity.deleted', ['name' => $name]), 'media:'.$before['id'], before: $before);

        $message = __('admin_media.flash.deleted', ['name' => $name]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('admin.media.index')->with('status', $message);
    }

    /* --- Library -------------------------------------------------------------------------------- */

    /**
     * View data of the library (full page, picker partial and slot chooser).
     *
     * @return array<string, mixed>
     */
    private function libraryData(Request $request): array
    {
        $services = self::serviceOptions();
        $search = $request->query('q');
        $search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';
        $service = $request->query('service');
        $service = is_string($service) && isset($services[$service]) ? $service : null;
        $filter = $service !== null ? 'service' : (in_array($request->query('filter'), ['gallery', 'unused'], true) ? (string) $request->query('filter') : 'all');

        $slot = $request->query('slot');
        $slot = is_string($slot) && Slots::isValid($slot) ? $slot : null;
        $picker = $request->boolean('picker');
        $selected = filter_var($request->query('selected'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        // The no-JS spot chooser marks the spot's current photo.
        if ($slot !== null && $selected === false) {
            $selected = MediaSlot::query()->where('slot', $slot)->value('media_id') ?? false;
        }

        $redirectQuery = $request->query('redirect');
        $redirect = SafeUrl::internal(is_string($redirectQuery) ? $redirectQuery : null, route('admin.media.index'));
        // The page that opened the picker: where the picker's upload form returns without JavaScript.
        $opener = SafeUrl::internal($request->headers->get('referer'), route('admin.media.index'));

        $artistIds = self::artistIds();
        $query = Media::query();

        match ($filter) {
            'gallery' => $query->where('in_gallery', true),
            'unused' => self::whereUnused($query, $artistIds),
            'service' => $query->where('service_slug', $service),
            default => null,
        };

        if ($search !== '') {
            $query->where(function (Builder $where) use ($search): void {
                foreach (self::SEARCHED as $column) {
                    $where->orWhereLike($column, Text::like($search), caseSensitive: false);
                }
            });
        }

        /** @var LengthAwarePaginator $photos */
        $photos = $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return [
            'photos' => $photos,
            'cards' => $this->cards($photos->getCollection()->all(), $services, $artistIds),
            'services' => $services,
            'filter' => $filter,
            'search' => $search,
            'service' => $service,
            'counts' => [
                'all' => Media::query()->count(),
                'gallery' => Media::query()->where('in_gallery', true)->count(),
                'unused' => self::whereUnused(Media::query(), $artistIds)->count(),
            ],
            'picker' => $picker,
            'selected' => $selected === false ? null : $selected,
            'slot' => $slot,
            'slotLabel' => $slot !== null ? Slots::label($slot) : null,
            'redirect' => $redirect,
            'opener' => $opener,
            'viewUrl' => SafeUrl::internal(is_string(session('media_view_url')) ? session('media_view_url') : null, ''),
        ];
    }

    /**
     * One card per photo: the photo and where it appears (badges).
     *
     * @param  list<Media>  $photos
     * @param  array<string, string>  $services  slug ⇒ title
     * @param  list<int>  $artistIds
     * @return list<array<string, mixed>>
     */
    private function cards(array $photos, array $services, array $artistIds): array
    {
        $ids = array_map(fn (Media $media): int => (int) $media->getKey(), $photos);

        if ($ids === []) {
            return [];
        }

        $spots = MediaSlot::query()->whereIn('media_id', $ids)->orderBy('slot')->get(['slot', 'media_id'])->groupBy('media_id');
        $covers = CustomPage::query()->whereIn('cover_media_id', $ids)->pluck('cover_media_id')->countBy()->all();
        $artists = array_flip($artistIds);

        return array_map(function (Media $media) use ($services, $spots, $covers, $artists): array {
            $id = (int) $media->getKey();
            $slots = ($spots->get($id) ?? collect())->pluck('slot')->map(fn (string $slot): string => Slots::label($slot))->all();
            $service = is_string($media->service_slug) && $media->service_slug !== '' ? ($services[$media->service_slug] ?? $media->service_slug) : null;
            $card = [
                'media' => $media,
                'item' => $media->item(),
                'name' => self::displayName($media),
                'gallery' => (bool) $media->in_gallery,
                'service' => $service,
                'spots' => $slots,
                'covers' => (int) ($covers[$id] ?? 0),
                'artists' => isset($artists[$id]),
            ];
            $card['nowhere'] = ! $card['gallery'] && $service === null && $slots === [] && $card['covers'] === 0 && ! $card['artists'];

            return $card;
        }, $photos);
    }

    /**
     * Photos shown nowhere: not in the gallery, no service, no spot, no free-page cover, no artist page.
     *
     * @param  Builder<Media>  $query
     * @param  list<int>  $artistIds
     * @return Builder<Media>
     */
    private static function whereUnused(Builder $query, array $artistIds): Builder
    {
        $query->where('in_gallery', false)
            ->where(fn (Builder $where) => $where->whereNull('service_slug')->orWhere('service_slug', ''))
            ->whereNotIn('id', MediaSlot::query()->select('media_id'))
            ->whereNotIn('id', CustomPage::query()->whereNotNull('cover_media_id')->select('cover_media_id'));

        if ($artistIds !== []) {
            $query->whereNotIn('id', $artistIds);
        }

        return $query;
    }

    /**
     * Ids of the photos the artist pages use (docs/ARTISTS.md), when that module is installed.
     *
     * @return list<int>
     */
    private static function artistIds(): array
    {
        return class_exists(MediaUsage::class) ? array_values(array_map('intval', MediaUsage::usedIds())) : [];
    }

    /* --- Edit ------------------------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function editData(Media $media): array
    {
        $uploader = $media->uploaded_by ? User::query()->find($media->uploaded_by) : null;

        return [
            'media' => $media,
            'item' => $media->item(),
            'name' => self::displayName($media),
            'services' => self::serviceOptions(),
            'usage' => $this->usage($media),
            'uploaderName' => $uploader?->name,
        ];
    }

    /**
     * Where a photo appears: ['type', 'label', 'admin_url' (?string), 'public_url' (?string)].
     *
     * @return list<array{type: string, label: string, admin_url: string|null, public_url: string|null}>
     */
    private function usage(Media $media): array
    {
        $usage = [];

        if ($media->in_gallery) {
            $usage[] = [
                'type' => 'gallery',
                'label' => __('admin_media.usage.gallery'),
                'admin_url' => route('admin.gallery.index'),
                'public_url' => Route::has('gallery') ? route('gallery') : null,
            ];
        }

        if (is_string($media->service_slug) && $media->service_slug !== '') {
            $slug = $media->service_slug;
            $usage[] = [
                'type' => 'service',
                'label' => __('admin_media.usage.service', ['service' => self::serviceTitle($slug)]),
                'admin_url' => Route::has('admin.services.edit') && self::serviceExists($slug) ? route('admin.services.edit', $slug) : null,
                'public_url' => Route::has('services.show') && self::serviceExists($slug) ? route('services.show', $slug) : null,
            ];
        }

        foreach (MediaSlot::query()->where('media_id', $media->getKey())->orderBy('slot')->pluck('slot') as $slot) {
            $usage[] = [
                'type' => 'slot',
                'label' => __('admin_media.usage.slot', ['slot' => Slots::label($slot)]),
                'admin_url' => SlotController::adminUrl($slot),
                'public_url' => SlotController::pageUrl($slot),
            ];
        }

        foreach (CustomPage::query()->where('cover_media_id', $media->getKey())->orderBy('title_fr')->get() as $page) {
            $usage[] = [
                'type' => 'cover',
                'label' => __($page->is_published ? 'admin_media.usage.cover' : 'admin_media.usage.cover_draft', ['page' => $page->title()]),
                'admin_url' => Route::has('admin.pages.edit') ? route('admin.pages.edit', $page) : null,
                'public_url' => $page->is_published && Route::has('pages.custom') ? route('pages.custom', $page->slug) : null,
            ];
        }

        if (class_exists(MediaUsage::class)) {
            foreach (MediaUsage::for((int) $media->getKey()) as $place) {
                $usage[] = [
                    'type' => 'artists',
                    'label' => (string) ($place['label'] ?? ''),
                    'admin_url' => is_string($place['url'] ?? null) ? SafeUrl::internal($place['url'], '') ?: null : null,
                    'public_url' => null,
                ];
            }
        }

        return $usage;
    }

    /* --- Uploads --------------------------------------------------------------------------------- */

    /**
     * Where an uploaded photo now appears: [where (gallery|service|slot|library), message, public URL].
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function placement(Media $media, ?string $slot): array
    {
        if ($slot !== null) {
            return ['slot', __('admin_media.uploaded.slot', ['slot' => Slots::label($slot)]), SlotController::pageUrl($slot)];
        }

        $service = is_string($media->service_slug) && $media->service_slug !== '' ? $media->service_slug : null;

        if ($service !== null) {
            $url = Route::has('services.show') ? route('services.show', $service) : null;
            $key = $media->in_gallery ? 'service' : 'service_only';

            return [$key, __('admin_media.uploaded.'.$key, ['service' => self::serviceTitle($service)]), $url];
        }

        if ($media->in_gallery) {
            return ['gallery', __('admin_media.uploaded.gallery'), Route::has('gallery') ? route('gallery') : null];
        }

        return ['library', __('admin_media.uploaded.library'), null];
    }

    /**
     * [in_gallery, service_slug] from the destination choice (docs/CMS.md §13 C14), else from the
     * in_gallery / service_slug fields.
     *
     * @return array{0: bool, 1: string|null}
     */
    private static function destination(Request $request): array
    {
        $service = $request->filled('service_slug') ? (string) $request->input('service_slug') : null;

        return match ($request->input('destination')) {
            'gallery' => [true, null],
            'service' => [true, $service],
            'library' => [false, null],
            default => [$request->boolean('in_gallery'), $service],
        };
    }

    /**
     * The resized copies sent by the uploader: variants[480], variants[960]…
     *
     * @return array<int, UploadedFile>
     */
    private static function variants(Request $request): array
    {
        $variants = [];

        foreach ((array) $request->file('variants', []) as $width => $file) {
            if ($file instanceof UploadedFile && filter_var($width, FILTER_VALIDATE_INT) !== false) {
                $variants[(int) $width] = $file;
            }
        }

        return $variants;
    }

    /** A photo MediaManager refused: its reason's French message (admin_media.errors.{reason}). */
    private function refused(Request $request, InvalidImage $e, UploadedFile $photo, string $back): JsonResponse|RedirectResponse
    {
        $reason = $this->reason($e, $photo);
        $message = __('admin_media.errors.'.$reason, ['max' => self::megabytes($this->manager->maxUploadBytes())]);

        return $this->uploadFailed($request, $message, $back, [], $reason);
    }

    /** InvalidImage::REASONS + "heic": iPhone photos the server cannot read get their own help. */
    private function reason(InvalidImage $e, UploadedFile $photo): string
    {
        $reason = in_array($e->reason, InvalidImage::REASONS, true) ? $e->reason : 'not_image';

        if (in_array($reason, ['type', 'not_image'], true)) {
            $mime = rescue(fn () => (string) $photo->getMimeType(), '', false);
            $extension = strtolower((string) pathinfo($photo->getClientOriginalName(), PATHINFO_EXTENSION));

            if (in_array($mime, self::HEIC, true) || in_array($extension, ['heic', 'heif'], true)) {
                $reason = 'heic';
            }
        }

        return $reason;
    }

    /**
     * A refused upload: JSON 422 {message, errors} for the uploader, else back to the page with an
     * error flash (the upload form holds no typed text: nothing is lost, nothing big is flashed).
     *
     * @param  array<string, list<string>>  $errors
     * @param  string|null  $reason  why MediaManager refused the photo (the uploader offers "Réessayer" for interrupted / storage)
     */
    private function uploadFailed(Request $request, string $message, string $back, array $errors = [], ?string $reason = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter([
                'message' => $message,
                'reason' => $reason,
                'errors' => $errors ?: ['photo' => [$message]],
            ], fn (mixed $value): bool => $value !== null), 422);
        }

        return redirect()->to($back)->with('error', $message);
    }

    /** The form's "redirect" field when it is a page of this site, else $fallback. */
    private static function redirectInput(Request $request, string $fallback): string
    {
        $redirect = $request->input('redirect');

        return SafeUrl::internal(is_string($redirect) ? $redirect : null, $fallback);
    }

    /* --- Shared helpers (GalleryController, SlotController, views) ---------------------------------- */

    /**
     * Every service, hidden ones included, for the selects: slug ⇒ title (current language).
     *
     * @return array<string, string>
     */
    public static function serviceOptions(): array
    {
        $options = [];

        foreach (app(ServiceCatalog::class)->withHidden()->all() as $service) {
            $options[(string) $service['slug']] = (string) ($service['title'] ?? $service['slug']);
        }

        return $options;
    }

    /** A photo's name in the admin: its original file name, else "Photo n° 12". */
    public static function displayName(?Media $media): string
    {
        if ($media === null) {
            return '';
        }

        $name = is_string($media->original_name) ? trim($media->original_name) : '';

        return $name !== '' ? $name : __('admin_media.card.unnamed', ['id' => $media->getKey()]);
    }

    /**
     * The photo as the uploader and the picker receive it: MediaItem::toArray() + edit_url + name.
     *
     * @return array<string, mixed>
     */
    public static function json(Media $media): array
    {
        return $media->item()->toArray() + [
            'edit_url' => route('admin.media.edit', $media),
            'name' => self::displayName($media),
        ];
    }

    /**
     * The editable metadata of a photo, kept as a version in the activity log (docs/CMS.md §13 F30).
     *
     * @return array<string, mixed>
     */
    public static function metadata(Media $media): array
    {
        return [
            'id' => (int) $media->getKey(),
            'alt_fr' => $media->alt_fr,
            'alt_en' => $media->alt_en,
            'caption_fr' => $media->caption_fr,
            'caption_en' => $media->caption_en,
            'service_slug' => $media->service_slug,
            'in_gallery' => (bool) $media->in_gallery,
            'position' => (int) $media->position,
            'focal_x' => (int) $media->focal_x,
            'focal_y' => (int) $media->focal_y,
            'original_name' => $media->original_name,
        ];
    }

    /** "3,5" (French) / "3.5": megabytes with one decimal. */
    public static function megabytes(int $bytes): string
    {
        $value = rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');

        return app()->getLocale() === 'fr' ? str_replace('.', ',', $value) : $value;
    }

    private static function serviceTitle(string $slug): string
    {
        return self::serviceOptions()[$slug] ?? $slug;
    }

    private static function serviceExists(string $slug): bool
    {
        return app(ServiceCatalog::class)->withHidden()->has($slug);
    }

    /** A text field as stored: line endings normalised, trimmed, valid UTF-8 cut to the column; '' ⇒ null. */
    private static function text(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) Text::column((string) $value, PHP_INT_MAX)));

        return $value === '' ? null : Text::column($value, $max);
    }
}
