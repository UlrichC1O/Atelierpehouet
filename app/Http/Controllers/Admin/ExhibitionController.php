<?php

namespace App\Http\Controllers\Admin;

use App\Artists\ArtistDirectory;
use App\Artists\Dates;
use App\Artists\Http\Concerns\HandlesArtistForms;
use App\Artists\Http\EnsureArtistTables;
use App\Artists\MediaOptions;
use App\Artists\Photos;
use App\Cms\Media\InvalidImage;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Exhibition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The exhibitions of an artist page in the admin (docs/ARTISTS.md §6.1): list (current, upcoming,
 * past), create, edit, delete. A start date sets the year; an end date needs a start date.
 */
final class ExhibitionController extends Controller implements HasMiddleware
{
    use HandlesArtistForms, RendersInvalidForms;

    /** Fields of the form. */
    private const FIELDS = ['title_fr', 'title_en', 'kind', 'venue', 'city', 'year', 'starts_on', 'ends_on', 'url',
        'description_fr', 'description_en', 'media_id', 'is_published'];

    /** Years an exhibition may have: from 1900 to ten years ahead. */
    private const FIRST_YEAR = 1900;

    private const YEARS_AHEAD = 10;

    public static function middleware(): array
    {
        return [EnsureArtistTables::class];
    }

    public function index(Artist $artist): View
    {
        $artist->loadCount(['artworks', 'exhibitions']);

        return view('admin.artists.exhibitions.index', [
            'artist' => $artist,
            'groups' => self::groups($artist->exhibitions()->get()),
        ]);
    }

    public function create(Artist $artist): View
    {
        return view('admin.artists.exhibitions.form', $this->formData($artist, self::blank()));
    }

    public function store(Request $request, Artist $artist): RedirectResponse|Response
    {
        $validator = Validator::make(self::input($request, self::FIELDS), self::rules(), self::validationMessages(), self::attributeNames());

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.artists.exhibitions.form', $this->formData($artist, self::blank()), $validator);
        }

        $exhibition = new Exhibition;
        $exhibition->artist_id = (int) $artist->getKey();
        $this->fillFrom($exhibition, self::withBooleans($request, $validator->validated()));
        $exhibition->save();

        $replace = ['title' => $exhibition->title_fr, 'name' => $artist->name];
        self::record('exhibition_created', $replace, $artist);

        return redirect()->route('admin.artists.exhibitions.index', $artist)
            ->with('status', self::message('flash.exhibition_created', $replace));
    }

    public function edit(Artist $artist, Exhibition $exhibition): View
    {
        return view('admin.artists.exhibitions.form', $this->formData($artist, $exhibition));
    }

    public function update(Request $request, Artist $artist, Exhibition $exhibition): RedirectResponse|Response
    {
        $validator = Validator::make(
            self::input($request, self::FIELDS, partial: true),
            self::sometimes(self::rules(), partial: true),
            self::validationMessages(),
            self::attributeNames(),
        );

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.artists.exhibitions.form', $this->formData($artist, $exhibition), $validator);
        }

        $this->fillFrom($exhibition, self::withBooleans($request, $validator->validated()));
        $exhibition->save();

        $replace = ['title' => $exhibition->title_fr, 'name' => $artist->name];
        self::record('exhibition_updated', $replace, $artist);

        return redirect()->route('admin.artists.exhibitions.index', $artist)
            ->with('status', self::message('flash.exhibition_updated', $replace));
    }

    /**
     * A new visual sent from the computer or the phone (`photo` + `variants[w]` + `original_name`: the CMS
     * uploader — JSON — or a plain multipart form), applied at once (docs/ARTISTS.md §6.1). It replaces the
     * current visual's file when nothing else on the site uses it, else it becomes a new library photo.
     */
    public function image(Request $request, Artist $artist, Exhibition $exhibition): JsonResponse|RedirectResponse
    {
        $back = route('admin.artists.exhibitions.edit', [$artist, $exhibition]);
        $photo = self::photo($request);

        if ($photo === null) {
            return self::refuse($request, self::message('errors.photo_required'), ['photo' => [self::message('errors.photo_required')]], 422, $back);
        }

        if (($manager = Photos::manager()) === null) {
            return self::refuse($request, self::failureMessage(), [], 503, $back);
        }

        $originalName = self::clean($request->input('original_name')) ?? $photo->getClientOriginalName();

        try {
            [$media, $replaced] = Photos::put($manager, $exhibition->media_id, $photo, self::variants($request), ['exhibitions' => [(int) $exhibition->getKey()]], [
                'alt_fr' => mb_substr((string) $exhibition->title_fr, 0, 300),
                'alt_en' => $exhibition->title_en === null ? null : mb_substr($exhibition->title_en, 0, 300),
                'original_name' => mb_substr($originalName, 0, 255),
            ], self::userId($request));
        } catch (InvalidImage $e) {
            return self::refuse($request, self::imageError($e), ['photo' => [self::imageError($e)]], 422, $back);
        }

        try {
            $exhibition->media_id = (int) $media->getKey();
            $replaced ? $exhibition->touch() : $exhibition->save();
        } catch (Throwable $e) {
            if (! $replaced) {
                Photos::delete([(int) $media->getKey()]);
            }

            throw $e;
        }

        $replace = ['title' => $exhibition->title_fr, 'name' => $artist->name];
        $message = self::message('flash.exhibition_image', $replace);
        self::record('exhibition_image', $replace, $artist);

        if ($request->expectsJson()) {
            return response()->json(['media' => Photos::json($media), 'message' => $message], 201);
        }

        return redirect()->to($back)->with('status', $message);
    }

    public function destroy(Artist $artist, Exhibition $exhibition): RedirectResponse
    {
        $replace = ['title' => $exhibition->title_fr, 'name' => $artist->name];

        $exhibition->delete();
        self::record('exhibition_deleted', $replace, $artist);

        return redirect()->route('admin.artists.exhibitions.index', $artist)
            ->with('status', self::message('flash.exhibition_deleted', $replace));
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function rules(): array
    {
        $last = (int) Carbon::now()->year + self::YEARS_AHEAD;

        return [
            'title_fr' => ['required', 'string', 'max:160'],
            'title_en' => ['nullable', 'string', 'max:160'],
            'kind' => ['nullable', 'string', Rule::in(Exhibition::KINDS)],
            'venue' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            // The year is typed for year-only entries; a start date gives it otherwise.
            'year' => ['nullable', 'required_without:starts_on', 'integer', 'between:'.self::FIRST_YEAR.','.$last],
            'starts_on' => ['nullable', 'required_with:ends_on', 'date_format:Y-m-d', 'after_or_equal:'.self::FIRST_YEAR.'-01-01',
                'before_or_equal:'.$last.'-12-31'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'url' => ['nullable', 'bail', 'string', 'max:255', 'url:https', self::httpsUrl()],
            'description_fr' => ['nullable', 'string', 'max:1500'],
            'description_en' => ['nullable', 'string', 'max:1500'],
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Applies validated values: kind defaults to "group", a new exhibition is published unless told
     * otherwise, the year follows the start date, and an end date without a start date is dropped.
     *
     * @param  array<string, mixed>  $valid
     */
    private function fillFrom(Exhibition $exhibition, array $valid): void
    {
        $attributes = [];

        foreach ($valid as $field => $value) {
            $attributes[$field] = match ($field) {
                'kind' => $value ?? 'group',
                'year', 'media_id' => $value === null ? null : (int) $value,
                'is_published' => $value === null ? ! $exhibition->exists || $exhibition->is_published : $value === true,
                default => $value,
            };
        }

        if (array_key_exists('year', $attributes) && $attributes['year'] === null) {
            unset($attributes['year']); // the start date gives it
        }

        $exhibition->fill($attributes);

        if ($exhibition->starts_on === null) {
            $exhibition->ends_on = null;
        } else {
            $exhibition->year = (int) $exhibition->starts_on->year;
        }
    }

    /**
     * The validated values with "is_published" as a PHP bool read by $request->boolean() (Postgres,
     * docs/ARTISTS.md §12.5); null when the form did not say.
     *
     * @param  array<string, mixed>  $valid
     * @return array<string, mixed>
     */
    private static function withBooleans(Request $request, array $valid): array
    {
        if (array_key_exists('is_published', $valid)) {
            $valid['is_published'] = $valid['is_published'] === null ? null : $request->boolean('is_published');
        }

        return $valid;
    }

    /** A new exhibition as the creation form shows it. */
    private static function blank(): Exhibition
    {
        return new Exhibition(['kind' => 'group', 'year' => (int) Carbon::now()->year, 'is_published' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Artist $artist, Exhibition $exhibition): array
    {
        $artist->loadCount(['artworks', 'exhibitions']);

        return [
            'artist' => $artist,
            'exhibition' => $exhibition,
            'media' => ArtistDirectory::media($exhibition->media_id),
            'mediaOptions' => MediaOptions::list($exhibition->media_id),
            'kinds' => Exhibition::KINDS,
        ];
    }

    /**
     * The exhibitions by status on today's date, ordered like the public page: current by end date,
     * upcoming by start date, past by year then start date (latest first).
     *
     * @param  Collection<int, Exhibition>  $exhibitions
     * @return array{current: Collection<int, Exhibition>, upcoming: Collection<int, Exhibition>, past: Collection<int, Exhibition>}
     */
    private static function groups(Collection $exhibitions): array
    {
        $today = Carbon::now();
        $groups = [Dates::CURRENT => [], Dates::UPCOMING => [], Dates::PAST => []];

        foreach ($exhibitions as $exhibition) {
            $groups[$exhibition->status($today)][] = $exhibition;
        }

        $day = fn (Exhibition $exhibition, string $field): ?string => $exhibition->getAttribute($field)?->format('Y-m-d');
        // Dates in order, a missing date last either way.
        $earliest = fn (?string $a, ?string $b): int => $a === $b ? 0 : ($a === null ? 1 : ($b === null ? -1 : strcmp($a, $b)));
        $latest = fn (?string $a, ?string $b): int => $a === $b ? 0 : ($a === null ? 1 : ($b === null ? -1 : strcmp($b, $a)));

        return [
            'current' => collect($groups[Dates::CURRENT])->sort(fn (Exhibition $a, Exhibition $b): int => $earliest($day($a, 'ends_on'), $day($b, 'ends_on'))
                ?: $earliest($day($a, 'starts_on'), $day($b, 'starts_on')) ?: $a->getKey() <=> $b->getKey())->values(),
            'upcoming' => collect($groups[Dates::UPCOMING])->sort(fn (Exhibition $a, Exhibition $b): int => $earliest($day($a, 'starts_on'), $day($b, 'starts_on'))
                ?: $a->year <=> $b->year ?: $a->getKey() <=> $b->getKey())->values(),
            'past' => collect($groups[Dates::PAST])->sort(fn (Exhibition $a, Exhibition $b): int => $b->year <=> $a->year
                ?: $latest($day($a, 'starts_on'), $day($b, 'starts_on')) ?: $b->getKey() <=> $a->getKey())->values(),
        ];
    }
}
