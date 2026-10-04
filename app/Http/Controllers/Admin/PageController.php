<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Activity;
use App\Cms\Markdown;
use App\Cms\MediaItem;
use App\Cms\Text;
use App\Http\Controllers\Admin\Concerns\RendersInvalidForms;
use App\Http\Controllers\Controller;
use App\Models\CustomPage;
use App\Models\Media;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * « Pages libres » (docs/CMS.md §7.4, §13 E21/F28): pages with a Markdown body served at /{slug}
 * by the public CustomPageController — legal notice, news, events…
 *
 * The slug is suggested from the French title and must be free: not another page's, not a reserved
 * word of config('cms.reserved_slugs'), not the first segment of any registered route. "Aperçu"
 * (preview=1) validates and shows the rendered Markdown without saving. Invalid forms come back
 * with HTTP 422 (RendersInvalidForms), never through the 4 KB cookie session.
 */
final class PageController extends Controller
{
    use RendersInvalidForms;

    /** Longest Markdown body accepted (docs/CMS.md §13 E21). */
    public const MAX_BODY = 20000;

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Legal pages created unpublished by the core migration (§13 F28), shown first in the list. */
    public const LEGAL = ['mentions-legales', 'confidentialite'];

    /** Columns of the form, in the order they are logged. */
    private const FIELDS = ['slug', 'title_fr', 'title_en', 'body_fr', 'body_en', 'meta_fr', 'meta_en', 'cover_media_id', 'is_published', 'in_footer', 'position'];

    public function index(): View
    {
        $pages = CustomPage::query()->orderBy('position')->orderBy('title_fr')->orderBy('id')
            ->get(['id', 'slug', 'title_fr', 'title_en', 'is_published', 'in_footer', 'position', 'cover_media_id', 'updated_at']);

        $legal = [];

        foreach (self::LEGAL as $slug) {
            $legal[$slug] = $pages->firstWhere('slug', $slug);
        }

        return view('admin.pages.index', [
            'pages' => $pages,
            'legal' => $legal,
            'covers' => $this->coverItems($pages->pluck('cover_media_id')->filter()->map(fn ($id): int => (int) $id)->all()),
        ]);
    }

    public function create(Request $request): View
    {
        $page = new CustomPage;

        // "Créer" from the legal pages card: the expected slug and title are suggested.
        $slug = (string) $request->query('slug', '');

        if (in_array($slug, self::LEGAL, true)) {
            $page->slug = $slug;
            $page->title_fr = (string) __('admin_content.pages.legal.names.'.$slug, [], 'fr');
            $page->title_en = (string) __('admin_content.pages.legal.names.'.$slug, [], 'en');
            $page->in_footer = true;
        }

        return view('admin.pages.form', $this->formData($page));
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $page = new CustomPage;
        $validator = $this->validator($request, $page);

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.pages.form', $this->formData($page), $validator);
        }

        $attributes = $this->attributes($validator->validated(), $page);

        if ($request->boolean('preview')) {
            return $this->preview($request, $page, $attributes);
        }

        $page->fill($attributes + ['updated_by' => $this->userId($request)]);
        $page->save();

        Activity::record('pages.create', __('admin_content.pages.activity.create', ['title' => $page->title_fr]), 'page:'.$page->id,
            after: $this->snapshot($page));

        return redirect()->route('admin.pages.edit', $page)
            ->with('status', __($page->is_published ? 'admin_content.pages.created' : 'admin_content.pages.created_draft', ['title' => $page->title_fr]));
    }

    public function edit(CustomPage $page): View
    {
        return view('admin.pages.form', $this->formData($page));
    }

    public function update(Request $request, CustomPage $page): RedirectResponse|Response
    {
        $validator = $this->validator($request, $page);

        if ($validator->fails()) {
            return $this->invalid($request, 'admin.pages.form', $this->formData($page), $validator);
        }

        $attributes = $this->attributes($validator->validated(), $page);

        if ($request->boolean('preview')) {
            return $this->preview($request, $page, $attributes);
        }

        $before = $this->snapshot($page);
        $page->fill($attributes);

        if (! $page->isDirty()) {
            return redirect()->route('admin.pages.edit', $page)->with('status', __('admin.flash.nothing'));
        }

        $page->updated_by = $this->userId($request);
        $page->save();

        Activity::record('pages.update', __('admin_content.pages.activity.update', ['title' => $page->title_fr]), 'page:'.$page->id,
            before: $before, after: $this->snapshot($page));

        return redirect()->route('admin.pages.edit', $page)->with('status', __('admin_content.pages.saved', ['title' => $page->title_fr]));
    }

    public function destroy(CustomPage $page): RedirectResponse
    {
        $before = $this->snapshot($page);
        $page->delete();

        Activity::record('pages.delete', __('admin_content.pages.activity.delete', ['title' => $before['title_fr']]), 'page:'.$page->id,
            before: $before);

        return redirect()->route('admin.pages.index')->with('status', __('admin_content.pages.deleted', ['title' => $before['title_fr']]));
    }

    /**
     * True when $slug can never be a free page's address: a reserved word (config cms.reserved_slugs)
     * or the first URL segment of a registered route (other than the free-page catch-all itself).
     */
    public static function reserved(string $slug): bool
    {
        if (in_array($slug, array_map('strval', (array) config('cms.reserved_slugs', [])), true)) {
            return true;
        }

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];

            if ($first !== '' && ! str_contains($first, '{') && strcasecmp($first, $slug) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Aperçu": the submitted values shown again with their rendered Markdown, nothing saved (HTTP 200,
     * the values replayed for this request only, like RendersInvalidForms).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function preview(Request $request, CustomPage $page, array $attributes): Response
    {
        if ($request->hasSession()) {
            $request->session()->now('_old_input', $request->except(['_token', '_method', 'preview']));
        }

        return response()->view('admin.pages.form', $this->formData($page, [
            'fr' => $attributes['body_fr'],
            'en' => $attributes['body_en'],
        ]));
    }

    private function validator(Request $request, CustomPage $page): ValidatorContract
    {
        $data = $request->only(['title_fr', 'title_en', 'slug', 'body_fr', 'body_en', 'meta_fr', 'meta_en', 'cover_media_id', 'position']);

        // Line endings as "\n" before measuring (browsers send "\r\n").
        foreach (['body_fr', 'body_en', 'meta_fr', 'meta_en'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = str_replace(["\r\n", "\r"], "\n", $data[$field]);
            }
        }

        $data['is_published'] = $request->boolean('is_published');
        $data['in_footer'] = $request->boolean('in_footer');
        $data['slug'] = is_string($data['slug'] ?? null) ? Str::lower(trim($data['slug'])) : null;

        $validator = Validator::make($data, [
            'title_fr' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80'],
            'body_fr' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'body_en' => ['nullable', 'string', 'max:'.self::MAX_BODY],
            'meta_fr' => ['nullable', 'string', 'max:170'],
            'meta_en' => ['nullable', 'string', 'max:170'],
            'cover_media_id' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['boolean'],
            'in_footer' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], (array) __('admin_content.pages.attributes'));

        $validator->after(function (ValidatorContract $validator) use ($page): void {
            $data = $validator->getData();
            $errors = $validator->errors();

            if (! $errors->has('cover_media_id') && is_numeric($data['cover_media_id'] ?? null)
                && ! Media::query()->whereKey((int) $data['cover_media_id'])->exists()) {
                $errors->add('cover_media_id', __('admin_content.pages.errors.cover'));
            }

            if ($errors->has('slug') || $errors->has('title_fr')) {
                return;
            }

            $typed = is_string($data['slug'] ?? null) && $data['slug'] !== '';
            $slug = $typed ? $data['slug'] : self::slugFrom((string) ($data['title_fr'] ?? ''));

            if ($slug === '') {
                $errors->add('slug', __('admin_content.pages.errors.slug_empty'));
            } elseif ($typed && preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                $errors->add('slug', __('admin_content.pages.errors.slug_format'));
            } elseif ($typed && self::reserved($slug)) {
                $errors->add('slug', __('admin_content.pages.errors.slug_reserved'));
            } elseif ($typed && $this->taken($slug, $page)) {
                $errors->add('slug', __('admin_content.pages.errors.slug_taken'));
            }
        });

        return $validator;
    }

    /**
     * Model attributes from the validated input: strings scrubbed and cut to their column, empty
     * optional texts stored as null, the slug suggested from the French title when left empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, CustomPage $page): array
    {
        $text = fn (string $field, int $max): ?string => is_string($data[$field] ?? null) && trim($data[$field]) !== ''
            ? Text::column($data[$field], $max)
            : null;

        $slug = is_string($data['slug'] ?? null) && $data['slug'] !== ''
            ? $data['slug']
            : $this->freeSlug(self::slugFrom((string) $data['title_fr']), $page);

        return [
            'slug' => $slug,
            'title_fr' => (string) Text::column(trim((string) $data['title_fr']), 120),
            'title_en' => $text('title_en', 120) === null ? null : trim((string) $text('title_en', 120)),
            'body_fr' => $text('body_fr', self::MAX_BODY),
            'body_en' => $text('body_en', self::MAX_BODY),
            'meta_fr' => $text('meta_fr', 170) === null ? null : trim((string) $text('meta_fr', 170)),
            'meta_en' => $text('meta_en', 170) === null ? null : trim((string) $text('meta_en', 170)),
            'cover_media_id' => is_numeric($data['cover_media_id'] ?? null) ? (int) $data['cover_media_id'] : null,
            'is_published' => (bool) $data['is_published'],
            'in_footer' => (bool) $data['in_footer'],
            'position' => is_numeric($data['position'] ?? null) ? (int) $data['position'] : 0,
        ];
    }

    /** A slug made from a title ("Mentions légales" ⇒ "mentions-legales"), at most 80 characters. */
    public static function slugFrom(string $title): string
    {
        // "L’atelier" ⇒ "l-atelier" (Str::slug alone would glue the elided article: "latelier").
        $slug = Str::slug(str_replace(['’', "'", 'ʼ'], ' ', $title), '-', 'fr');

        if (strlen($slug) > 80) {
            $slug = rtrim(substr($slug, 0, 80), '-');
        }

        return preg_match(self::SLUG_PATTERN, $slug) === 1 ? $slug : '';
    }

    /** $slug, or $slug-2, $slug-3… when it is reserved or another page's (suggested slugs only). */
    private function freeSlug(string $slug, CustomPage $page): string
    {
        $candidate = $slug;

        for ($i = 2; self::reserved($candidate) || $this->taken($candidate, $page); $i++) {
            $suffix = '-'.$i;
            $candidate = rtrim(substr($slug, 0, 80 - strlen($suffix)), '-').$suffix;
        }

        return $candidate;
    }

    private function taken(string $slug, CustomPage $page): bool
    {
        return CustomPage::query()->where('slug', $slug)
            ->when($page->exists, fn ($query) => $query->whereKeyNot($page->getKey()))
            ->exists();
    }

    /**
     * Data of the create/edit form.
     *
     * @param  array{fr: ?string, en: ?string}|null  $previewBodies  submitted bodies to render instead of the saved ones
     * @return array<string, mixed>
     */
    private function formData(CustomPage $page, ?array $previewBodies = null): array
    {
        $bodies = $previewBodies ?? ['fr' => $page->body_fr, 'en' => $page->body_en];

        $media = Media::query()->orderByDesc('id')
            ->get(['id', 'ulid', 'mime', 'extension', 'width', 'height', 'size', 'original_name', 'variants', 'alt_fr', 'alt_en',
                'caption_fr', 'caption_en', 'focal_x', 'focal_y', 'service_slug', 'in_gallery', 'position', 'updated_at'])
            ->map(fn (Media $item): MediaItem => MediaItem::fromModel($item))
            ->all();

        return [
            'page' => $page,
            'photos' => $media,
            'cover' => $page->cover_media_id ? $this->findItem($media, (int) $page->cover_media_id) : null,
            'previewing' => $previewBodies !== null,
            'rendered' => [
                'fr' => Markdown::render($bodies['fr']),
                'en' => Markdown::render($bodies['en']),
            ],
            'publicUrl' => $page->exists ? route('pages.custom', $page->slug) : null,
            'siteRoot' => rtrim(url('/'), '/').'/',
        ];
    }

    /**
     * @param  list<MediaItem>  $items
     */
    private function findItem(array $items, int $id): ?MediaItem
    {
        foreach ($items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Thumbnails of the covers listed on the index.
     *
     * @param  list<int>  $ids
     * @return array<int, MediaItem>
     */
    private function coverItems(array $ids): array
    {
        $items = [];

        foreach ($ids as $id) {
            if (($item = cms()->media($id)) !== null) {
                $items[$id] = $item;
            }
        }

        return $items;
    }

    /**
     * The page as logged in the activity (restorable version, docs/CMS.md §13 F30).
     *
     * @return array<string, mixed>
     */
    private function snapshot(CustomPage $page): array
    {
        $values = ['id' => $page->id];

        foreach (self::FIELDS as $field) {
            $values[$field] = $page->getAttribute($field);
        }

        return $values;
    }

    private function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
