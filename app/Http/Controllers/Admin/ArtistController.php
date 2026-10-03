<?php

namespace App\Http\Controllers\Admin;

use App\Artists\ArtistDirectory;
use App\Artists\ExampleArtist;
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
use App\Models\Exhibition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The artist pages in the admin (docs/ARTISTS.md §6.1): list and order, create, edit (identity,
 * portrait, biography, publication), publish/hide, delete (optionally with their photos), and the
 * example artist.
 *
 * Invalid forms are shown again with HTTP 422 (RendersInvalidForms: no input in the 4 KB cookie
 * session); every write is recorded in the activity log; the models flush the public cache.
 */
final class ArtistController extends Controller implements HasMiddleware
{
    use HandlesArtistForms, RendersInvalidForms;

    /** Fields of the creation form. */
    private const CREATE_FIELDS = ['name', 'slug', 'discipline_fr', 'discipline_en', 'location', 'accent'];

    /** Fields of the edit form. */
    private const EDIT_FIELDS = ['name', 'slug', 'discipline_fr', 'discipline_en', 'location', 'accent', 'website', 'instagram',
        'statement_fr', 'statement_en', 'bio_fr', 'bio_en', 'meta_fr', 'meta_en', 'portrait_media_id', 'is_published'];

    public static function middleware(): array
    {
        return [EnsureArtistTables::class];
    }

    public function index(): View
    {
        $artists = Artist::query()->ordered()->withCount(['artworks', 'exhibitions'])->with('artworks')->get();

        return view('admin.artists.index', [
            'artists' => $artists,
            'hasExample' => $artists->contains(fn (Artist $artist): bool => $artist->is_example),
        ]);
    }

    public function create(): View
    {
        return view('admin.artists.create', $this->createData());
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $data = self::input($request, self::CREATE_FIELDS);
        $data['slug'] = $data['slug'] === null ? null : mb_strtolower($data['slug']);

        // No slug typed: one is made from the name, numbered when it is already taken.
        if ($data['slug'] === null && $data['name'] !== null) {
            $data['slug'] = $this->freeSlug(self::slugify($data['name']) ?: 'artiste');
        }

        $validator = Validator::make($data, $this->identityRules(), self::validationMessages(), self::attributeNames());

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.artists.create', $this->createData(), $validator);
        }

        $valid = $validator->validated();

        $artist = Artist::query()->create([
            'name' => $valid['name'],
            'slug' => $valid['slug'],
            'discipline_fr' => $valid['discipline_fr'] ?? null,
            'discipline_en' => $valid['discipline_en'] ?? null,
            'location' => $valid['location'] ?? null,
            'accent' => $valid['accent'] ?? Artist::DEFAULT_ACCENT,
            'is_published' => false,
            'position' => (int) Artist::query()->max('position') + 1,
            'updated_by' => self::userId($request),
        ]);

        self::record('created', ['name' => $artist->name], $artist);

        return redirect()->route('admin.artists.edit', $artist)
            ->with('status', self::message('flash.created', ['name' => $artist->name]));
    }

    public function edit(Artist $artist): View
    {
        return view('admin.artists.edit', $this->editData($artist));
    }

    public function update(Request $request, Artist $artist): RedirectResponse|Response
    {
        $data = self::input($request, self::EDIT_FIELDS, partial: true);

        if (array_key_exists('slug', $data) && $data['slug'] !== null) {
            $data['slug'] = mb_strtolower($data['slug']);
        }

        $rules = self::sometimes($this->identityRules($artist) + [
            'website' => ['nullable', 'bail', 'string', 'max:255', 'url:https', self::httpsUrl()],
            'instagram' => ['nullable', 'bail', 'string', 'max:255', 'url:https', self::httpsUrl()],
            'statement_fr' => ['nullable', 'string', 'max:400'],
            'statement_en' => ['nullable', 'string', 'max:400'],
            'bio_fr' => ['nullable', 'string', 'max:10000'],
            'bio_en' => ['nullable', 'string', 'max:10000'],
            'meta_fr' => ['nullable', 'string', 'max:170'],
            'meta_en' => ['nullable', 'string', 'max:170'],
            'portrait_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'is_published' => ['required', 'boolean'],
        ], partial: true);

        $validator = Validator::make($data, $rules, self::validationMessages(), self::attributeNames());

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.artists.edit', $this->editData($artist), $validator);
        }

        $attributes = [];

        foreach ($validator->validated() as $field => $value) {
            $attributes[$field] = match ($field) {
                'accent' => $value ?? Artist::DEFAULT_ACCENT,
                'portrait_media_id' => $value === null ? null : (int) $value,
                'is_published' => $request->boolean('is_published'), // a PHP bool (Postgres, §12.5)
                default => $value,
            };
        }

        $artist->fill($attributes);
        $artist->updated_by = self::userId($request);
        $artist->save();

        self::record('updated', ['name' => $artist->name], $artist);

        return redirect()->route('admin.artists.edit', $artist)
            ->with('status', self::message('flash.updated', ['name' => $artist->name]));
    }

    /**
     * The portrait, outside the profile form, applied at once (docs/ARTISTS.md §6.1): a new photo sent
     * from the computer or the phone (`photo` + `variants[w]` + `original_name`, the CMS uploader — JSON —
     * or a plain multipart form), else a photo of the library (`media_id`, empty = no portrait).
     * A new file replaces the current portrait's file when nothing else on the site uses it, else it
     * becomes a new photo of the library (App\Artists\Photos::put()).
     */
    public function portrait(Request $request, Artist $artist): JsonResponse|RedirectResponse
    {
        $back = route('admin.artists.edit', $artist);
        $photo = self::photo($request);

        if ($photo === null) {
            return $this->choosePortrait($request, $artist, $back);
        }

        if (($manager = Photos::manager()) === null) {
            return self::refuse($request, self::failureMessage(), [], 503, $back);
        }

        $originalName = self::clean($request->input('original_name')) ?? $photo->getClientOriginalName();

        try {
            [$media, $replaced] = Photos::put($manager, $artist->portrait_media_id, $photo, self::variants($request), ['artists' => [(int) $artist->getKey()]], [
                'alt_fr' => mb_substr((string) __('artists.show.portrait_alt', ['name' => $artist->name], 'fr'), 0, 300),
                'alt_en' => mb_substr((string) __('artists.show.portrait_alt', ['name' => $artist->name], 'en'), 0, 300),
                'original_name' => mb_substr($originalName, 0, 255),
            ], self::userId($request));
        } catch (InvalidImage $e) {
            return self::refuse($request, self::imageError($e), ['photo' => [self::imageError($e)]], 422, $back);
        }

        try {
            $artist->portrait_media_id = (int) $media->getKey();
            $artist->updated_by = self::userId($request);
            $replaced ? $artist->touch() : $artist->save();
        } catch (Throwable $e) {
            if (! $replaced) {
                Photos::delete([(int) $media->getKey()]);
            }

            throw $e;
        }

        $message = self::message('flash.portrait_updated', ['name' => $artist->name]);
        self::record('portrait_updated', ['name' => $artist->name], $artist);

        if ($request->expectsJson()) {
            return response()->json(['media' => Photos::json($media), 'message' => $message], 201);
        }

        return redirect()->to($back)->with('status', $message);
    }

    /** The portrait chosen in the library (or none): `media_id` of the portrait card's form. */
    private function choosePortrait(Request $request, Artist $artist, string $back): JsonResponse|RedirectResponse
    {
        $data = ['media_id' => self::clean($request->input('media_id'))];
        $validator = Validator::make($data, [
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
        ], self::validationMessages(), self::attributeNames());

        if ($validator->fails()) {
            return self::refuse($request, (string) $validator->errors()->first(), $validator->errors(), 422, $back);
        }

        $id = $data['media_id'] === null ? null : (int) $data['media_id'];
        $artist->portrait_media_id = $id;
        $artist->updated_by = self::userId($request);
        $artist->save();

        $key = $id === null ? 'portrait_removed' : 'portrait_updated';
        $message = self::message('flash.'.$key, ['name' => $artist->name]);
        self::record($key, ['name' => $artist->name], $artist);

        if ($request->expectsJson()) {
            return response()->json(['media' => Photos::json(Photos::model($id)), 'message' => $message]);
        }

        return redirect()->to($back)->with('status', $message);
    }

    public function toggle(Request $request, Artist $artist): JsonResponse|RedirectResponse
    {
        $artist->is_published = ! $artist->is_published;
        $artist->updated_by = self::userId($request);
        $artist->save();

        $state = $artist->is_published ? 'published' : 'hidden';
        $message = self::message('flash.'.$state, ['name' => $artist->name]);

        self::record($state, ['name' => $artist->name], $artist);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'published' => $artist->is_published]);
        }

        return redirect()->to(self::redirectTarget($request, url()->previous(route('admin.artists.index'))))->with('status', $message);
    }

    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $order = $request->input('order');
        $order = is_array($order) ? self::moved($order, $request->input('move')) : $order; // the arrows without JavaScript
        $ids = is_array($order) ? array_values(array_unique(array_map('intval', array_filter($order, 'is_numeric')))) : [];
        $known = $ids === [] ? [] : Artist::query()->whereIn('id', $ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($ids === [] || ! is_array($order) || count($ids) !== count($order) || count($known) !== count($ids)) {
            return self::refuse($request, self::message('errors.order'), ['order' => [self::message('errors.order')]]);
        }

        $rest = Artist::query()->ordered()->whereNotIn('id', $ids)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        DB::transaction(function () use ($ids, $rest): void {
            foreach ([...$ids, ...$rest] as $index => $id) {
                Artist::query()->whereKey($id)->toBase()->update(['position' => $index + 1]);
            }
        });

        app(ArtistDirectory::class)->flush();

        $message = self::message('flash.reordered', [], count($ids));
        self::record('reordered', [], null, count($ids));

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back(fallback: route('admin.artists.index'))->with('status', $message);
    }

    public function destroy(Request $request, Artist $artist): RedirectResponse
    {
        $photos = $request->boolean('delete_photos') ? $this->deletablePhotos($artist) : [];
        $name = $artist->name;

        // The rows of the artwork and exhibition tables go too (also without foreign key enforcement).
        DB::transaction(function () use ($artist): void {
            Artwork::query()->where('artist_id', $artist->getKey())->delete();
            Exhibition::query()->where('artist_id', $artist->getKey())->delete();
            $artist->delete();
        });

        Photos::delete($photos);
        self::record('deleted', ['name' => $name], $artist);

        return redirect()->route('admin.artists.index')->with('status', self::message('flash.deleted', ['name' => $name]));
    }

    public function example(Request $request): RedirectResponse
    {
        $example = app(ExampleArtist::class);
        $existed = $example->exists();

        try {
            $artist = $example->create(self::userId($request));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.artists.index')->with('error', self::message('errors.example'));
        }

        // The flash says how many photos came with it (docs/ARTISTS.md §12.8: a refused photo is skipped).
        $message = $existed
            ? self::message('flash.example_exists', ['name' => $artist->name])
            : self::message('flash.example_created', ['name' => $artist->name], $this->photoCount($artist));

        return redirect()->route('admin.artists.edit', $artist)->with('status', $message);
    }

    /**
     * Rules of the identity fields (creation form, and the identity card of the edit form).
     *
     * @return array<string, list<mixed>>
     */
    private function identityRules(?Artist $artist = null): array
    {
        $unique = Rule::unique('artists', 'slug');

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [$artist === null ? 'nullable' : 'required', 'string', 'max:'.Artist::SLUG_MAX,
                'regex:/^'.Artist::SLUG_PATTERN.'$/', $artist === null ? $unique : $unique->ignore($artist->getKey())],
            'discipline_fr' => ['nullable', 'string', 'max:120'],
            'discipline_en' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'accent' => ['nullable', 'string', Rule::in(self::accents())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createData(): array
    {
        return [
            'artist' => new Artist(['accent' => Artist::DEFAULT_ACCENT]),
            'accents' => self::accents(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editData(Artist $artist): array
    {
        $artist->loadCount(['artworks', 'exhibitions']);

        return [
            'artist' => $artist,
            'portrait' => ArtistDirectory::media($artist->portrait_media_id),
            'mediaOptions' => MediaOptions::list($artist->portrait_media_id),
            'accents' => self::accents(),
        ];
    }

    /** $base, else $base-2, $base-3… (the first slug no artist uses). */
    private function freeSlug(string $base): string
    {
        $slug = $base;

        for ($n = 2; Artist::query()->where('slug', $slug)->exists(); $n++) {
            $suffix = '-'.$n;
            $slug = rtrim(substr($base, 0, Artist::SLUG_MAX - strlen($suffix)), '-').$suffix;
        }

        return $slug;
    }

    /** Number of distinct photos of an artist page (portrait, artworks, exhibitions). */
    private function photoCount(Artist $artist): int
    {
        try {
            $ids = [
                $artist->portrait_media_id,
                ...Artwork::query()->where('artist_id', $artist->getKey())->whereNotNull('media_id')->pluck('media_id')->all(),
                ...Exhibition::query()->where('artist_id', $artist->getKey())->whereNotNull('media_id')->pluck('media_id')->all(),
            ];
        } catch (Throwable) {
            return 0;
        }

        return count(array_unique(array_map('intval', array_filter($ids, fn (mixed $id): bool => is_numeric($id) && (int) $id > 0))));
    }

    /**
     * The photos of an artist (portrait, artworks, exhibitions) used nowhere else on the site.
     *
     * @return list<int>
     */
    private function deletablePhotos(Artist $artist): array
    {
        $artworks = Artwork::query()->where('artist_id', $artist->getKey())->get(['id', 'media_id']);
        $exhibitions = Exhibition::query()->where('artist_id', $artist->getKey())->get(['id', 'media_id']);

        $ids = array_unique(array_filter(
            [$artist->portrait_media_id, ...$artworks->pluck('media_id')->all(), ...$exhibitions->pluck('media_id')->all()],
            fn (mixed $id): bool => is_numeric($id) && (int) $id > 0,
        ));

        $ignore = [
            'artists' => [(int) $artist->getKey()],
            'artworks' => $artworks->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            'exhibitions' => $exhibitions->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        ];

        return array_values(array_map('intval', array_filter($ids, fn (mixed $id): bool => MediaUsage::deletable((int) $id, $ignore))));
    }
}
