<?php

namespace App\Http\Controllers;

use App\Cms\Cms;
use App\Cms\Markdown;
use App\Http\Middleware\ApplyCms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Throwable;

/**
 * Free pages created in the CMS (docs/CMS.md §7.4), served at /{slug} — the last explicit route.
 *
 * Published pages come from the CMS snapshot (their body is read on demand and cached). An unknown
 * or unpublished slug is a 404 — except for a signed-in administrator, who sees the page with a
 * preview banner. The administrator is recognised from the session first (no query for visitors),
 * then by the "admin" gate. While the CMS cannot read its data at all, the cms.data middleware turns
 * the 404 into a 503 (docs/CMS.md §13 A3).
 */
final class CustomPageController extends Controller
{
    /** Length of the description built from the body when the page has none. */
    private const META_LENGTH = 160;

    public function show(Request $request, Cms $cms, string $slug): View
    {
        $page = $cms->page($slug);
        $preview = false;

        if ($page === null && ApplyCms::signedIn($request) && $this->isAdmin()) {
            $page = $cms->preview($slug);
            $preview = $page !== null && ! ($page['published'] ?? false);
        }

        abort_if($page === null, 404);

        $locale = app()->getLocale();
        $title = self::localized($page['title'], $locale) ?? $slug;
        $body = self::localized($page['body'] ?? [], $locale);
        $bodyLocale = self::filled($page['body'][$locale] ?? null) ? $locale : 'fr';
        $cover = is_int($page['cover'] ?? null) ? $cms->media($page['cover']) : null;
        $updated = $this->date($page['updated_at'] ?? null, $locale);

        return view('pages.custom', [
            'page' => $page,
            'title' => $title,
            'body' => Markdown::render($body),
            'bodyLocale' => $bodyLocale,
            'translated' => $bodyLocale === $locale,
            'meta' => self::localized($page['meta'] ?? [], $locale) ?? $this->excerpt($body),
            'cover' => $cover,
            'updated' => $updated,
            'preview' => $preview,
        ]);
    }

    /** The administrator right of the signed-in account (false when it cannot be read). */
    private function isAdmin(): bool
    {
        try {
            return Gate::allows('admin');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The text in $locale, else the French one, else null.
     *
     * @param  array<string, mixed>  $texts
     */
    private static function localized(array $texts, string $locale): ?string
    {
        foreach ([$locale, 'fr'] as $candidate) {
            if (self::filled($texts[$candidate] ?? null)) {
                return (string) $texts[$candidate];
            }
        }

        return null;
    }

    private static function filled(mixed $text): bool
    {
        return is_string($text) && trim($text) !== '';
    }

    /** First words of the body as plain text (Markdown signs removed), or null. */
    private function excerpt(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) Markdown::render($body))));

        return $text === '' ? null : Str::limit(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), self::META_LENGTH);
    }

    /** "3 octobre 2026" / "October 3, 2026", or null. */
    private function date(?string $iso, string $locale): ?array
    {
        if ($iso === null) {
            return null;
        }

        try {
            $date = Carbon::parse($iso)->locale($locale);
        } catch (Throwable) {
            return null;
        }

        return [
            'iso' => $date->toDateString(),
            'label' => $date->translatedFormat($locale === 'en' ? 'F j, Y' : 'j F Y'),
        ];
    }
}
