<?php

namespace App\Artists\Http\Concerns;

use App\Cms\Activity;
use App\Cms\Media\InvalidImage;
use App\Cms\SafeUrl;
use App\Models\Artist;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared helpers of the admin controllers of the artist pages (docs/ARTISTS.md §6): normalised input,
 * validation attribute names and messages, messages (admin_artists.*), the activity log, the files
 * sent by the CMS photo uploader, safe redirects and external links (§12.7). Every call into the CMS
 * core is guarded.
 */
trait HandlesArtistForms
{
    /**
     * The submitted values of $fields, normalised (see clean()). With $partial, only the fields present
     * in the request: an update changes only what its form sends.
     *
     * @param  list<string>  $fields
     * @return array<string, string|null>
     */
    protected static function input(Request $request, array $fields, bool $partial = false): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($partial && ! $request->has($field)) {
                continue;
            }

            $data[$field] = self::clean($request->input($field));
        }

        return $data;
    }

    /**
     * Trimmed text with "\n" line endings, valid UTF-8 and no NUL byte (Postgres refuses both);
     * blank (or not a scalar) ⇒ null.
     */
    protected static function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], mb_scrub((string) $value, 'UTF-8'));
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Prefixes every rule list with "sometimes" for a partial update.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, list<mixed>>
     */
    protected static function sometimes(array $rules, bool $partial): array
    {
        return $partial ? array_map(fn (array $list): array => ['sometimes', ...$list], $rules) : $rules;
    }

    /**
     * Human names of the validated fields (admin_artists.attributes).
     *
     * @return array<string, string>
     */
    protected static function attributeNames(): array
    {
        $names = Lang::has('admin_artists.attributes') ? __('admin_artists.attributes') : [];

        return is_array($names) ? array_filter($names, 'is_string') : [];
    }

    /**
     * Plain-language messages for the rules where the generic ones of validation.php would puzzle
     * the owner (admin_artists.validation.*). Keys the forms do not use are harmless.
     *
     * @return array<string, string>
     */
    protected static function validationMessages(): array
    {
        $messages = [
            'slug.regex' => 'slug_regex',
            'slug.unique' => 'slug_unique',
            'year.required_without' => 'year_required',
            'starts_on.required_with' => 'starts_required',
            'ends_on.after_or_equal' => 'ends_after',
            'website.url' => 'https',
            'instagram.url' => 'https',
            'url.url' => 'https',
        ];

        return array_filter(array_map(
            fn (string $key): ?string => Lang::has('admin_artists.validation.'.$key) ? (string) __('admin_artists.validation.'.$key) : null,
            $messages,
        ));
    }

    /**
     * Validation rule of the external links (website, Instagram, exhibition page): an https address
     * that App\Cms\SafeUrl::external() accepts (docs/ARTISTS.md §12.7).
     */
    protected static function httpsUrl(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! is_string($value) || ! self::isExternalUrl($value)) {
                $fail(self::message('validation.https'));
            }
        };
    }

    /**
     * True for an absolute https URL without backslash, whitespace or control character —
     * App\Cms\SafeUrl::external() when the CMS provides it, else the same checks here.
     */
    public static function isExternalUrl(string $url): bool
    {
        if (class_exists(SafeUrl::class) && method_exists(SafeUrl::class, 'external')) {
            try {
                $safe = SafeUrl::external($url);

                return $safe === true || (is_string($safe) && $safe !== '');
            } catch (Throwable) {
                return false;
            }
        }

        return preg_match('#^https://#i', $url) === 1
            && preg_match('/[\\\\\s\x00-\x1f\x7f]/u', $url) !== 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Where a form asks to go back to (its "redirect" field), when that is a page of this site:
     * App\Cms\SafeUrl::internal() when the CMS provides it, else a same-host check. $fallback otherwise.
     */
    protected static function redirectTarget(Request $request, string $fallback): string
    {
        $url = $request->input('redirect');

        if (! is_string($url) || $url === '' || preg_match('/[\\\\\x00-\x20\x7f]/', $url) === 1) {
            return $fallback;
        }

        // An absolute address of this site becomes its path (SafeUrl::internal() takes paths).
        $root = rtrim(url('/'), '/');

        if ($url === $root || str_starts_with($url, $root.'/') || str_starts_with($url, $root.'?')) {
            $url = '/'.ltrim(substr($url, strlen($root)), '/');
        }

        if (class_exists(SafeUrl::class) && method_exists(SafeUrl::class, 'internal')) {
            try {
                $safe = SafeUrl::internal($url, $fallback);

                return is_string($safe) && $safe !== '' ? $safe : $fallback;
            } catch (Throwable) {
                return $fallback;
            }
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $fallback;
    }

    /**
     * The order sent by a list, after the "move up / move down" buttons of the page without
     * JavaScript (`move` = "{id}:up" or "{id}:down" swaps that item with its neighbour).
     *
     * @param  array<int|string, mixed>  $order
     * @return array<int, mixed>
     */
    protected static function moved(array $order, mixed $move): array
    {
        $order = array_values($order);

        if (! is_string($move) || preg_match('/^([0-9]{1,10}):(up|down)$/', $move, $match) !== 1) {
            return $order;
        }

        $ids = array_map(fn (mixed $id): string => is_scalar($id) ? (string) $id : '', $order);
        $index = array_search($match[1], $ids, true);

        if ($index === false) {
            return $order;
        }

        $target = $match[2] === 'up' ? $index - 1 : $index + 1;

        if ($target >= 0 && $target < count($order)) {
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
        }

        return $order;
    }

    /**
     * A line of admin_artists ("flash.created"…); lines with a count go through trans_choice().
     *
     * @param  array<string, mixed>  $replace
     */
    protected static function message(string $key, array $replace = [], ?int $count = null): string
    {
        $line = 'admin_artists.'.$key;

        return (string) ($count === null ? __($line, $replace) : trans_choice($line, $count, $replace + ['count' => $count]));
    }

    /** "Something went wrong" (the CMS's generic message). */
    protected static function failureMessage(): string
    {
        return (string) (Lang::has('admin.flash.error') ? __('admin.flash.error') : __('admin_artists.errors.migrate'));
    }

    /**
     * The error shown for a photo the library refuses: admin_media.errors.{reason} (the photo
     * library's own wording), else admin_artists.errors.photo.{reason}, else the generic failure.
     */
    protected static function imageError(Throwable $e): string
    {
        $reason = $e instanceof InvalidImage && preg_match('/^[a-z_]+$/', $e->reason) === 1 ? $e->reason : 'storage';

        foreach (['admin_media.errors.', 'admin_artists.errors.photo.'] as $prefix) {
            if (Lang::has($prefix.$reason)) {
                $line = __($prefix.$reason);

                if (is_string($line)) {
                    return $line;
                }
            }
        }

        return self::failureMessage();
    }

    /**
     * Records an admin activity "artists.{action}" (summary admin_artists.activity.{action}, subject
     * "artist:{id}"). Never throws.
     *
     * @param  array<string, mixed>  $replace
     */
    protected static function record(string $action, array $replace = [], ?Artist $artist = null, ?int $count = null): void
    {
        if (! class_exists(Activity::class)) {
            return;
        }

        try {
            Activity::record('artists.'.$action, self::message('activity.'.$action, $replace, $count), $artist === null ? null : 'artist:'.$artist->getKey());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Id of the logged-in admin. */
    protected static function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Accent keys an artist may use.
     *
     * @return list<string>
     */
    protected static function accents(): array
    {
        return array_values(array_map('strval', (array) config('atelier.accents', [])));
    }

    /**
     * The resized copies sent by the CMS uploader: width ⇒ file (MediaManager keeps the valid ones).
     *
     * @return array<int, UploadedFile>
     */
    protected static function variants(Request $request): array
    {
        $variants = [];
        $files = $request->file('variants');

        foreach (is_array($files) ? $files : [] as $width => $file) {
            if (is_numeric($width) && $file instanceof UploadedFile) {
                $variants[(int) $width] = $file;
            }
        }

        return $variants;
    }

    /** The uploaded "photo", or null (none, or several files under that name). */
    protected static function photo(Request $request): ?UploadedFile
    {
        $photo = $request->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    /** A slug made of the words of $value, at most Artist::SLUG_MAX characters ('' when none). */
    protected static function slugify(string $value): string
    {
        return trim(substr(Str::slug($value), 0, Artist::SLUG_MAX), '-');
    }

    /**
     * The answer to a script (JSON {message, errors}) or the redirect for a form, for errors that are
     * not shown inside a form (reordering, an unavailable photo library…).
     *
     * @param  array<string, string|list<string>>|MessageBag  $errors
     */
    protected static function refuse(Request $request, string $message, array|MessageBag $errors = [], int $status = 422, ?string $fallback = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $bag = $errors instanceof MessageBag ? $errors : new MessageBag($errors);

            return response()->json(['message' => $message, 'errors' => (object) $bag->toArray()], $status);
        }

        return back(fallback: $fallback ?? route('admin.artists.index'))->with('error', $message);
    }
}
