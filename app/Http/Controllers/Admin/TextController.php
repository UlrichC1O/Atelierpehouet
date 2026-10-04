<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Cms\Cms;
use App\Cms\OverridingTranslationLoader;
use App\Cms\Placeholders;
use App\Cms\Slots;
use App\Cms\Text;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\TranslationOverride;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;

/**
 * « Textes des pages » (docs/CMS.md §7.3, §13 F27): every text of the editable translation groups,
 * French and English side by side. The lang/ files stay the originals; a changed text is stored as
 * a TranslationOverride row, deleted again when the text goes back to the original (emptied field,
 * same text, or "Rétablir l'original").
 *
 * The form is validated by hand: its keys contain dots ("hero.title"), which Laravel's validator
 * would read as nesting. An invalid form is shown again with HTTP 422 and nothing saved — never
 * flashed into the 4 KB cookie session.
 */
final class TextController extends Controller
{
    use RendersInvalidForms;

    /** Longest text accepted. */
    public const MAX_LENGTH = 5000;

    /** Longer originals (or with a line break) get a textarea. */
    private const SINGLE_LINE = 120;

    /** Soft limit shown under the Google descriptions. */
    private const META_LENGTH = 160;

    /** Leaves that are settings, not copy (icon names): never shown nor saved. */
    private const TECHNICAL = ['icon'];

    public function index(): View
    {
        $groups = $this->groups();
        $known = [];

        foreach (array_keys($groups) as $group) {
            $known[$group] = array_flip(array_keys($this->leaves('fr', $group)));
        }

        // Number of texts changed per group (a text changed in both languages counts once).
        $modified = array_fill_keys(array_keys($groups), []);

        foreach (TranslationOverride::query()->whereIn('group', array_keys($groups))->get(['group', 'key']) as $row) {
            if (isset($known[$row->group][$row->key])) {
                $modified[$row->group][$row->key] = true;
            }
        }

        $cards = [];

        foreach ($groups as $group => $route) {
            $cards[] = [
                'group' => $group,
                'label' => $this->groupLabel($group),
                'text' => $this->groupText($group),
                'count' => count($known[$group]),
                'modified' => count($modified[$group]),
                'url' => route('admin.texts.edit', $group),
                'view' => $this->pageUrl($route),
                'slots' => $this->slots($group, route('admin.texts.index')),
            ];
        }

        return view('admin.texts.index', ['cards' => $cards]);
    }

    public function edit(string $group): View
    {
        $this->ensureEditable($group);

        return view('admin.texts.edit', $this->editData($group));
    }

    public function update(Request $request, string $group): RedirectResponse|Response
    {
        $this->ensureEditable($group);

        $locales = $this->locales();
        $defaults = $this->defaults($group);
        $current = $this->overrides($group);
        $submitted = $request->input('t');
        $resets = $request->input('reset');
        $errors = new MessageBag;
        $changes = [];

        foreach ($locales as $locale) {
            $values = is_array($submitted[$locale] ?? null) ? $submitted[$locale] : [];
            $reset = is_array($resets[$locale] ?? null) ? $resets[$locale] : [];

            foreach (array_keys($values + $reset) as $key) {
                $key = (string) $key;

                // Only the string leaves of the file: stale or invented keys are ignored.
                if (! array_key_exists($key, $defaults[$locale])) {
                    continue;
                }

                $default = $defaults[$locale][$key];
                $old = $current[$locale][$key] ?? null;

                if (filter_var($reset[$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $new = null;
                } elseif (! array_key_exists($key, $values)) {
                    continue;
                } else {
                    $value = $values[$key];

                    if ($value !== null && ! is_string($value)) {
                        $errors->add($this->errorKey($locale, $key), __('admin_content.texts.errors.invalid'));

                        continue;
                    }

                    $value = self::normalize($value);

                    if (mb_strlen($value) > self::MAX_LENGTH) {
                        $errors->add($this->errorKey($locale, $key), __('admin_content.texts.errors.too_long', ['max' => self::MAX_LENGTH]));

                        continue;
                    }

                    $value = (string) Text::column($value, self::MAX_LENGTH);

                    // Empty, or the original again: no override.
                    if (trim($value) === '' || trim($value) === trim(self::normalize($default))) {
                        $new = null;
                    } elseif (($missing = Placeholders::missing($default, $value)) !== []) {
                        $errors->add($this->errorKey($locale, $key), $this->placeholderMessage($missing));

                        continue;
                    } else {
                        $new = $value;
                    }
                }

                if ($new !== $old) {
                    $changes[] = ['locale' => $locale, 'key' => $key, 'old' => $old, 'new' => $new];
                }
            }
        }

        if ($errors->isNotEmpty()) {
            return $this->invalid($request, 'admin.texts.edit', $this->editData($group, [
                't' => is_array($submitted) ? $submitted : [],
                'reset' => is_array($resets) ? $resets : [],
            ]), $errors);
        }

        if ($changes === []) {
            return redirect()->route('admin.texts.edit', $group)->with('status', __('admin_content.texts.nothing'));
        }

        $this->save($group, $changes, $request->user()?->getAuthIdentifier());

        $count = count($changes);
        $before = $after = [];

        foreach ($changes as $change) {
            $before[$change['locale']][$change['key']] = $change['old'];
            $after[$change['locale']][$change['key']] = $change['new'];
        }

        Activity::record(
            'texts.update',
            trans_choice('admin_content.texts.activity', $count, ['count' => $count, 'group' => $this->groupLabel($group)]),
            'texts:'.$group,
            before: ['group' => $group, 'texts' => $before],
            after: ['group' => $group, 'texts' => $after],
        );

        return redirect()->route('admin.texts.edit', $group)
            ->with('status', trans_choice('admin_content.texts.saved', $count, ['count' => $count]));
    }

    /**
     * Editable groups of the editor, in menu order ⇒ route of their public page (or null): those of
     * Cms::editableGroups() whose French file exists and whose page route is registered (a module
     * not installed yet — artists, photos, notices — is skipped).
     *
     * @return array<string, string|null>
     */
    public function groups(): array
    {
        return array_filter(
            Cms::editableGroups(),
            fn (?string $route, string $group): bool => is_file(lang_path('fr/'.$group.'.php')) && ($route === null || Route::has($route)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Upserts and deletes the overrides in one transaction, then drops the public snapshot (query
     * builder writes fire no model event).
     *
     * @param  list<array{locale: string, key: string, old: string|null, new: string|null}>  $changes
     */
    private function save(string $group, array $changes, mixed $userId): void
    {
        $userId = is_numeric($userId) ? (int) $userId : null;
        $upserts = [];
        $deletes = [];

        foreach ($changes as $change) {
            if ($change['new'] === null) {
                $deletes[$change['locale']][] = $change['key'];
            } else {
                $upserts[] = [
                    'locale' => $change['locale'],
                    'group' => $group,
                    'key' => $change['key'],
                    'value' => $change['new'],
                    'updated_by' => $userId,
                ];
            }
        }

        DB::transaction(function () use ($group, $upserts, $deletes): void {
            if ($upserts !== []) {
                TranslationOverride::query()->upsert($upserts, ['locale', 'group', 'key'], ['value', 'updated_by']);
            }

            foreach ($deletes as $locale => $keys) {
                TranslationOverride::query()->where('group', $group)->where('locale', $locale)->whereIn('key', $keys)->delete();
            }
        });

        cms()->flush();
    }

    /**
     * Everything the edit screen shows: sections of rows, counters, photo spots.
     *
     * @param  array{t?: array<mixed>, reset?: array<mixed>}  $submitted  values of an invalid form
     * @return array<string, mixed>
     */
    private function editData(string $group, array $submitted = []): array
    {
        $locales = $this->locales();
        $defaults = $this->defaults($group);
        $overrides = $this->overrides($group);
        $sections = [];
        $modified = 0;

        foreach ($defaults['fr'] as $key => $frenchDefault) {
            $cells = [];
            $rowModified = false;

            foreach ($locales as $locale) {
                $default = $defaults[$locale][$key] ?? '';
                $override = $overrides[$locale][$key] ?? null;
                // A refused form shows what was sent — an emptied field stays empty.
                $sent = is_array($submitted['t'][$locale] ?? null) && array_key_exists($key, $submitted['t'][$locale])
                    ? $submitted['t'][$locale][$key]
                    : false;
                $value = $sent === false ? ($override ?? $default) : (is_string($sent) ? $sent : '');
                $missing = array_values(array_filter(
                    Placeholders::missing($default, ''),
                    fn (string $token): bool => $token !== '|',
                ));

                $cells[$locale] = [
                    'name' => 't['.$locale.']['.$key.']',
                    'reset_name' => 'reset['.$locale.']['.$key.']',
                    'id' => 'field-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->errorKey($locale, $key)), '-'),
                    'error_key' => $this->errorKey($locale, $key),
                    'default' => $default,
                    'override' => $override,
                    'value' => $value,
                    'reset' => filter_var($submitted['reset'][$locale][$key] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'multiline' => mb_strlen($default) > self::SINGLE_LINE || str_contains($default, "\n")
                        || mb_strlen($value) > self::SINGLE_LINE || str_contains($value, "\n"),
                    'placeholders' => $missing,
                    'plural' => str_contains($default, '|'),
                ];

                $rowModified = $rowModified || $override !== null;
            }

            $modified += $rowModified ? 1 : 0;
            $segments = explode('.', $key);
            $section = count($segments) > 1 ? $segments[0] : '';

            $sections[$section] ??= [
                'key' => $section,
                'title' => $this->sectionTitle($group, $section),
                'rows' => [],
            ];

            $current = $overrides['fr'][$key] ?? $frenchDefault;
            $label = $this->label($group, $key);
            $isMeta = $this->isMeta($key);

            $sections[$section]['rows'][] = [
                'key' => $key,
                'anchor' => 'text-'.trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $key), '-'),
                'label' => $label ?? ($isMeta ? __('admin_content.texts.meta_label') : Str::limit(trim((string) preg_replace('/\s+/u', ' ', $current)), 70)),
                'hint' => $label === null && ! $isMeta ? $key : null,
                'meta' => $isMeta,
                'modified' => $rowModified,
                'cells' => $cells,
                'search' => implode(' ', array_filter([$label, $key, ...array_column($cells, 'default')])),
            ];
        }

        $route = $this->groups()[$group] ?? null;

        return [
            'group' => $group,
            'label' => $this->groupLabel($group),
            'text' => $this->groupText($group),
            'sections' => array_values($sections),
            'total' => count($defaults['fr']),
            'modified' => $modified,
            'locales' => $locales,
            'view' => $this->pageUrl($route),
            'slots' => $this->slots($group, route('admin.texts.edit', $group)),
            'metaLength' => self::META_LENGTH,
        ];
    }

    /**
     * The photo spots shown on a group's page (config cms.slots, "page" = the group).
     *
     * @return list<array{slot: string, label: string, media: mixed, ratio: string, redirect: string}>
     */
    private function slots(string $group, string $redirect): array
    {
        $slots = [];

        foreach (Slots::forPage($group) as $key => $slot) {
            $slots[] = [
                'slot' => $key,
                'label' => Slots::label($key),
                'media' => cms()->slot($key),
                'ratio' => $slot['ratio'],
                'redirect' => $redirect,
            ];
        }

        return $slots;
    }

    /** URL of a group's public page (null without route, or when the route needs parameters). */
    private function pageUrl(?string $route): ?string
    {
        return $route === null ? null : rescue(fn (): string => route($route), null, false);
    }

    private function ensureEditable(string $group): void
    {
        abort_unless(array_key_exists($group, $this->groups()), 404);
    }

    /**
     * Original texts of a group per locale (dot key ⇒ text, file order). The French file decides
     * which texts exist; a text missing from another language's file falls back to the French one,
     * as the site itself does.
     *
     * @return array<string, array<string, string>>
     */
    private function defaults(string $group): array
    {
        $french = $this->leaves('fr', $group);
        $defaults = [];

        foreach ($this->locales() as $locale) {
            $lines = $locale === 'fr' ? $french : $this->leaves($locale, $group);
            $defaults[$locale] = [];

            foreach ($french as $key => $text) {
                $defaults[$locale][$key] = $lines[$key] ?? $text;
            }
        }

        return $defaults;
    }

    /**
     * The string leaves of a group's file (without the CMS overrides), technical leaves excluded.
     *
     * @return array<string, string>
     */
    private function leaves(string $locale, string $group): array
    {
        $lines = $this->files()->load($locale, $group);
        $leaves = [];

        foreach (Arr::dot(is_array($lines) ? $lines : []) as $key => $value) {
            $key = (string) $key;

            if (is_string($value) && ! in_array(Str::afterLast($key, '.'), self::TECHNICAL, true)) {
                $leaves[$key] = $value;
            }
        }

        return $leaves;
    }

    /** The lang/ files themselves, without the overrides. */
    private function files(): Loader
    {
        $loader = app('translation.loader');

        return $loader instanceof OverridingTranslationLoader ? $loader->files() : $loader;
    }

    /**
     * Saved overrides of a group.
     *
     * @return array<string, array<string, string>> locale ⇒ key ⇒ value
     */
    private function overrides(string $group): array
    {
        $overrides = [];

        foreach (TranslationOverride::query()->where('group', $group)->get(['locale', 'key', 'value']) as $row) {
            $overrides[(string) $row->locale][(string) $row->key] = (string) $row->value;
        }

        return $overrides;
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $locales = array_map('strval', array_keys((array) config('atelier.locales', ['fr' => 'Français', 'en' => 'English'])));

        return in_array('fr', $locales, true) ? $locales : ['fr', ...$locales];
    }

    /** Key of a field in the error bag (and in its id): t.{locale}.{dot.key}, as <x-admin.field> builds it. */
    private function errorKey(string $locale, string $key): string
    {
        return 't.'.$locale.'.'.$key;
    }

    /**
     * The French explanation of missing placeholders: which token, and what the site puts there.
     *
     * @param  list<string>  $missing
     */
    private function placeholderMessage(array $missing): string
    {
        $tokens = array_values(array_filter($missing, fn (string $token): bool => $token !== '|'));

        if ($tokens === []) {
            return __('admin_content.texts.errors.plural');
        }

        $meanings = array_map($this->meaning(...), $tokens);

        $message = count($tokens) === 1
            ? __('admin_content.texts.errors.placeholder', ['token' => $tokens[0], 'meaning' => $meanings[0]])
            : __('admin_content.texts.errors.placeholders', [
                'tokens' => implode(', ', array_map(fn (string $token): string => '« '.$token.' »', $tokens)),
                'meanings' => implode(', ', array_unique($meanings)),
            ]);

        return in_array('|', $missing, true) ? $message.' '.__('admin_content.texts.errors.plural') : $message;
    }

    /** What the site writes in place of a placeholder (":count" ⇒ "un nombre calculé par le site"). */
    private function meaning(string $token): string
    {
        $name = strtolower(trim($token, ':{}'));
        $key = 'admin_content.texts.placeholders.'.$name;

        return app('translator')->has($key) ? __($key) : __('admin_content.texts.placeholders.default');
    }

    private function groupLabel(string $group): string
    {
        $key = 'admin_content.groups.'.$group.'.label';

        return app('translator')->has($key) ? __($key) : Str::headline($group);
    }

    private function groupText(string $group): ?string
    {
        $key = 'admin_content.groups.'.$group.'.text';

        return app('translator')->has($key) ? __($key) : null;
    }

    private function sectionTitle(string $group, string $section): string
    {
        if ($section === '') {
            return __('admin_content.texts.general');
        }

        $key = 'admin_content.sections.'.$group.'.'.$section;
        $title = app('translator')->has($key) ? __($key) : null;

        return is_string($title) ? $title : Str::ucfirst(str_replace(['_', '-'], ' ', $section));
    }

    /** admin_content.labels.{group}.{key} when it is a text, else null. */
    private function label(string $group, string $key): ?string
    {
        $translationKey = 'admin_content.labels.'.$group.'.'.$key;
        $label = app('translator')->has($translationKey) ? __($translationKey) : null;

        return is_string($label) && $label !== '' ? $label : null;
    }

    /** A Google description ("meta", "meta.description", "index.meta"…). */
    private function isMeta(string $key): bool
    {
        return in_array('meta', explode('.', $key), true) || str_ends_with($key, 'meta_description');
    }

    /** Line endings as "\n", whatever the browser sent. */
    private static function normalize(?string $value): string
    {
        return str_replace(["\r\n", "\r"], "\n", (string) $value);
    }
}
