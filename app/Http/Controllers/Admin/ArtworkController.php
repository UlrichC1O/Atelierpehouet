<?php

namespace App\Http\Controllers\Admin;

use App\Artists\ArtistDirectory;
use App\Artists\Http\Concerns\HandlesArtistForms;
use App\Artists\Http\EnsureArtistTables;
use App\Artists\MediaOptions;
use App\Artists\MediaUsage;
use App\Artists\Photos;
use App\Cms\Media\InvalidImage;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The artworks of an artist page in the admin (docs/ARTISTS.md §6.1): bulk upload (one artwork per
 * photo, through the CMS uploader or a plain form), edit, replace the photo file, order, delete.
 *
 * Photos are rows of the CMS library written by MediaManager only; the uploader receives the JSON
 * answers of docs/CMS.md §7.6 (201 {media, …} / 422 {message, errors}).
 */
final class ArtworkController extends Controller implements HasMiddleware
{
    use HandlesArtistForms, RendersInvalidForms;

    /** Fields of the edit form. */
    private const FIELDS = ['title_fr', 'title_en', 'year', 'medium_fr', 'medium_en', 'dimensions', 'description_fr',
        'description_en', 'availability', 'is_published', 'media_id'];

    public static function middleware(): array
    {
        return [EnsureArtistTables::class];
    }

    public function index(Artist $artist): View
    {
        return view('admin.artists.artworks.index', $this->indexData($artist));
    }

    /**
     * One new artwork from an uploaded photo (bulk uploads send one request per file) or from a photo
     * already in the library (media_id).
     */
    public function store(Request $request, Artist $artist): JsonResponse|RedirectResponse|Response
    {
        $photo = self::photo($request);
        $data = self::input($request, ['media_id', 'title_fr', 'original_name']);
        // The file's own name is not the owner's text: a very long one is cut, never refused.
        $data['original_name'] = $data['original_name'] === null ? null : mb_substr($data['original_name'], 0, 255);

        $validator = Validator::make($data, [
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'title_fr' => ['nullable', 'string', 'max:160'],
            'original_name' => ['nullable', 'string', 'max:255'],
        ], [], self::attributeNames());

        $validator->after(function ($validator) use ($photo, $data): void {
            if ($photo === null && $data['media_id'] === null) {
                $validator->errors()->add('photo', self::message('errors.photo_required'));
            }
        });

        if ($validator->fails()) {
            return $this->rejectUpload($request, $this->indexView($artist), $validator->errors());
        }

        $valid = $validator->validated();
        $stored = false;

        if ($photo !== null) {
            if (($manager = Photos::manager()) === null) {
                return self::refuse($request, self::failureMessage(), [], 503, route('admin.artists.artworks.index', $artist));
            }

            $originalName = $valid['original_name'] ?? $photo->getClientOriginalName();
            $title = $valid['title_fr'] ?? self::titleFrom($originalName);

            try {
                $media = $manager->store($photo, self::variants($request), [
                    'alt_fr' => $title,
                    'original_name' => $originalName,
                ], self::userId($request));
            } catch (InvalidImage $e) {
                return $this->rejectUpload($request, $this->indexView($artist), ['photo' => [self::imageError($e)]]);
            }

            $stored = true;
        } else {
            $media = Photos::model((int) $valid['media_id']);

            if ($media === null) {
                return $this->rejectUpload($request, $this->indexView($artist), ['media_id' => [self::message('errors.photo_required')]]);
            }

            $title = $valid['title_fr'] ?? self::titleFrom($media->original_name);
        }

        try {
            $artwork = Artwork::query()->create([
                'artist_id' => $artist->getKey(),
                'media_id' => (int) $media->getKey(),
                'title_fr' => $title,
                'availability' => 'none',
                'is_published' => true,
                'position' => (int) Artwork::query()->where('artist_id', $artist->getKey())->max('position') + 1,
            ]);
        } catch (Throwable $e) {
            if ($stored) {
                Photos::delete([(int) $media->getKey()]);
            }

            throw $e;
        }

        $replace = ['title' => $artwork->title_fr, 'name' => $artist->name];
        $message = self::message('flash.artwork_created', $replace);
        self::record('artwork_created', $replace, $artist);

        if ($request->expectsJson()) {
            return response()->json([
                'media' => Photos::json($media),
                'artwork' => [
                    'id' => $artwork->getKey(),
                    'title' => $artwork->title_fr,
                    'edit_url' => route('admin.artists.artworks.edit', [$artist, $artwork]),
                ],
                'message' => $message,
            ], 201);
        }

        return redirect()->route('admin.artists.artworks.edit', [$artist, $artwork])->with('status', $message);
    }

    public function edit(Artist $artist, Artwork $artwork): View
    {
        return view('admin.artists.artworks.edit', $this->editData($artist, $artwork));
    }

    public function update(Request $request, Artist $artist, Artwork $artwork): RedirectResponse|Response
    {
        $data = self::input($request, self::FIELDS, partial: true);

        $validator = Validator::make($data, self::sometimes([
            'title_fr' => ['required', 'string', 'max:160'],
            'title_en' => ['nullable', 'string', 'max:160'],
            'year' => ['nullable', 'string', 'max:20'],
            'medium_fr' => ['nullable', 'string', 'max:160'],
            'medium_en' => ['nullable', 'string', 'max:160'],
            'dimensions' => ['nullable', 'string', 'max:80'],
            'description_fr' => ['nullable', 'string', 'max:1500'],
            'description_en' => ['nullable', 'string', 'max:1500'],
            'availability' => ['nullable', 'string', Rule::in(Artwork::AVAILABILITIES)],
            'is_published' => ['required', 'boolean'],
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
        ], partial: true), self::validationMessages(), self::attributeNames());

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.artists.artworks.edit', $this->editData($artist, $artwork), $validator);
        }

        $before = ['fr' => $artwork->title_fr, 'en' => $artwork->title_en, 'media_id' => $artwork->media_id];
        $attributes = [];

        foreach ($validator->validated() as $field => $value) {
            $attributes[$field] = match ($field) {
                'availability' => $value ?? 'none',
                'is_published' => $request->boolean('is_published'), // a PHP bool (Postgres, §12.5)
                'media_id' => $value === null ? null : (int) $value,
                default => $value,
            };
        }

        $artwork->fill($attributes)->save();

        if ($artwork->media_id !== null && $artwork->media_id === $before['media_id']) {
            self::syncAlt($artwork, $before);
        }

        $replace = ['title' => $artwork->title_fr, 'name' => $artist->name];
        self::record('artwork_updated', $replace, $artist);

        return redirect()->route('admin.artists.artworks.edit', [$artist, $artwork])
            ->with('status', self::message('flash.artwork_updated', $replace));
    }

    /**
     * Replaces the photo file of an artwork (the CMS uploader, one file): the same photo of the library
     * gets the new file everywhere it is used; an artwork without a photo gets a new one.
     */
    public function image(Request $request, Artist $artist, Artwork $artwork): JsonResponse|RedirectResponse|Response
    {
        $photo = self::photo($request);
        $editView = fn (): array => ['admin.artists.artworks.edit', $this->editData($artist, $artwork)];

        if ($photo === null) {
            return $this->rejectUpload($request, $editView, ['photo' => [self::message('errors.photo_required')]]);
        }

        if (($manager = Photos::manager()) === null) {
            return self::refuse($request, self::failureMessage(), [], 503, route('admin.artists.artworks.edit', [$artist, $artwork]));
        }

        $originalName = self::clean($request->input('original_name'));
        $originalName = $originalName === null ? null : mb_substr($originalName, 0, 255);
        $media = Photos::model($artwork->media_id);

        try {
            if ($media !== null) {
                $media = $manager->replace($media, $photo, self::variants($request), $originalName);
                $artwork->touch();
            } else {
                $media = $manager->store($photo, self::variants($request), [
                    'alt_fr' => $artwork->title_fr,
                    'alt_en' => $artwork->title_en,
                    'original_name' => $originalName ?? $photo->getClientOriginalName(),
                ], self::userId($request));

                $this->link($artwork, $media);
            }
        } catch (InvalidImage $e) {
            return $this->rejectUpload($request, $editView, ['photo' => [self::imageError($e)]]);
        }

        $replace = ['title' => $artwork->title_fr, 'name' => $artist->name];
        $message = self::message('flash.image_replaced', $replace);
        self::record('image_replaced', $replace, $artist);

        if ($request->expectsJson()) {
            return response()->json(['media' => Photos::json($media), 'message' => $message], 201);
        }

        return redirect()->route('admin.artists.artworks.edit', [$artist, $artwork])->with('status', $message);
    }

    public function reorder(Request $request, Artist $artist): JsonResponse|RedirectResponse
    {
        $back = route('admin.artists.artworks.index', $artist);
        $order = $request->input('order');
        $order = is_array($order) ? self::moved($order, $request->input('move')) : $order; // the arrows without JavaScript
        $ids = is_array($order) ? array_values(array_unique(array_map('intval', array_filter($order, 'is_numeric')))) : [];
        $known = $ids === [] ? [] : Artwork::query()->where('artist_id', $artist->getKey())->whereIn('id', $ids)->pluck('id')->all();

        if ($ids === [] || ! is_array($order) || count($ids) !== count($order) || count($known) !== count($ids)) {
            return self::refuse($request, self::message('errors.order'), ['order' => [self::message('errors.order')]], 422, $back);
        }

        $rest = $artist->artworks()->whereNotIn('id', $ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        DB::transaction(function () use ($ids, $rest): void {
            foreach ([...$ids, ...$rest] as $index => $id) {
                Artwork::query()->whereKey($id)->toBase()->update(['position' => $index + 1]);
            }
        });

        $artist->touch(); // the page changed (and the public cache is flushed)

        $replace = ['name' => $artist->name];
        $message = self::message('flash.artworks_reordered', $replace, count($ids));
        self::record('artworks_reordered', $replace, $artist, count($ids));

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back(fallback: $back)->with('status', $message);
    }

    public function destroy(Request $request, Artist $artist, Artwork $artwork): RedirectResponse
    {
        $mediaId = $artwork->media_id;
        $deletePhoto = $mediaId !== null && $request->boolean('delete_photo')
            && MediaUsage::deletable((int) $mediaId, ['artworks' => [(int) $artwork->getKey()]]);
        $replace = ['title' => $artwork->title_fr, 'name' => $artist->name];

        $artwork->delete();

        if ($deletePhoto) {
            Photos::delete([(int) $mediaId]);
        }

        self::record('artwork_deleted', $replace, $artist);

        return redirect()->route('admin.artists.artworks.index', $artist)
            ->with('status', self::message('flash.artwork_deleted', $replace));
    }

    /**
     * A title made from a file name: "mon_tableau-02.jpg" ⇒ "Mon tableau 02"; "Sans titre" without one.
     */
    public static function titleFrom(?string $fileName): string
    {
        $name = pathinfo(basename(str_replace('\\', '/', (string) $fileName)), PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/[\s_\-.]+/u', ' ', $name));

        return $name === '' ? self::message('artworks.untitled') : Str::ucfirst(mb_substr($name, 0, 160));
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Artist $artist): array
    {
        $artist->loadCount(['artworks', 'exhibitions']);

        return [
            'artist' => $artist,
            'artworks' => $artist->artworks()->get()->map(fn (Artwork $artwork): array => [
                'model' => $artwork,
                'media' => ArtistDirectory::media($artwork->media_id),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editData(Artist $artist, Artwork $artwork): array
    {
        $artist->loadCount(['artworks', 'exhibitions']);

        return [
            'artist' => $artist,
            'artwork' => $artwork,
            'media' => ArtistDirectory::media($artwork->media_id),
            'mediaOptions' => MediaOptions::list($artwork->media_id),
            'availabilities' => Artwork::AVAILABILITIES,
        ];
    }

    /**
     * The works list, shown again (422) under a refused upload sent without JavaScript.
     *
     * @return callable(): array{0: string, 1: array<string, mixed>}
     */
    private function indexView(Artist $artist): callable
    {
        return fn (): array => ['admin.artists.artworks.index', $this->indexData($artist)];
    }

    /**
     * A refused upload: 422 JSON {message, errors} for the uploader, else the page shown again with
     * the errors (422).
     *
     * @param  callable(): array{0: string, 1: array<string, mixed>}  $view
     * @param  MessageBag|array<string, list<string>>  $errors
     */
    private function rejectUpload(Request $request, callable $view, MessageBag|array $errors): JsonResponse|Response
    {
        $bag = $errors instanceof MessageBag ? $errors : new MessageBag($errors);

        if ($request->expectsJson()) {
            return response()->json(['message' => (string) $bag->first(), 'errors' => $bag->toArray()], 422);
        }

        [$name, $data] = $view();

        return $this->invalid($request, $name, $data, $bag);
    }

    /** Links a newly stored photo to the artwork (the photo is deleted again when that fails). */
    private function link(Artwork $artwork, Media $media): void
    {
        try {
            $artwork->media_id = (int) $media->getKey();
            $artwork->save();
        } catch (Throwable $e) {
            Photos::delete([(int) $media->getKey()]);

            throw $e;
        }
    }

    /**
     * Keeps the photo's alternative texts in step with the title when they were only its copy (the
     * uploader's default): an alt equal to the old title follows the new title, and an empty English
     * alt takes a new English title. Texts the owner wrote in the library are never touched.
     *
     * @param  array{fr: string|null, en: string|null, media_id: int|null}  $before
     */
    private static function syncAlt(Artwork $artwork, array $before): void
    {
        $media = Photos::model($artwork->media_id);

        if ($media === null) {
            return;
        }

        $changes = [];

        foreach (['fr', 'en'] as $locale) {
            $old = $before[$locale];
            $new = $artwork->getAttribute('title_'.$locale);
            $alt = self::clean($media->getAttribute('alt_'.$locale));

            if ($new === $old) {
                continue;
            }

            if (($alt !== null && $old !== null && $alt === trim($old)) || ($alt === null && $old === null && $locale === 'en')) {
                $changes['alt_'.$locale] = $new === null ? null : mb_substr($new, 0, 300);
            }
        }

        if ($changes === []) {
            return;
        }

        try {
            $media->forceFill($changes)->save();
        } catch (Throwable $e) {
            Log::warning('Artwork photo alt text not updated: '.$e->getMessage());
        }
    }
}
