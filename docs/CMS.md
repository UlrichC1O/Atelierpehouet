# Ateliers Pehouet — Admin CMS contract

> Binding contract for the content-management back office ("l'administration"). It extends
> `docs/ARCHITECTURE.md` (whose rules still apply: logo palette only, `ap-` keyframes with `@anim`
> comments, no inline `<script>`/`on*=`, all copy through `__()` with identical fr/en keys, French first).
> When code and this document disagree, fix the code or update this document deliberately.
> **§13 (amendments after the design review) overrides every earlier section where they differ.**

The owner of the atelier logs in at **`/admin`** and can, without touching code:

1. **Edit every text of the site** (all pages, menus, footer, buttons, errors, e-mail) in French and English.
2. **Edit the services**: every field of each service page, order, visibility, category/accent/icon/art style;
   **create new services** (they get a generic animated scene) and delete the ones they created.
3. **Add photos and change photos**: a photo library (upload from computer or phone, drag & drop, multiple),
   alt text/captions FR+EN, focal point, **replace a photo's file** everywhere it is used, delete;
   photos appear in the public **gallery**, on **service pages** ("Réalisations"), as **service cover**, and in
   fixed **photo spots** of the pages (about, community, home, default share image).
4. **Create free pages** (legal notice, news, events…) with a simple Markdown body, cover photo, footer link.
5. **Manage contact requests** (mini CRM): inbox of quote requests, status, internal notes, reply by e-mail.
6. **Site settings**: contact details, social links, announcement banner.
7. **Account & admins**, **maintenance** (database update button, cache flush, JSON export), activity log.

---

## 1. Principles (non-negotiable)

- **The public site never breaks because of the CMS.** Files stay the defaults: `lang/{fr,en}/*.php` and
  `resources/content/services/*.php` are the original content; the CMS stores only **overrides** (and the
  things it creates). If the database is unreachable, paused (Supabase free plan), slow, or the CMS tables
  do not exist yet (migrations pending), every public page renders with the file defaults — no 500, no
  visible error, a bounded wait (circuit breaker + `DB_TIMEOUT`, §13 A1), and a failed load is negatively cached
  (stale-if-error, §13 A3).
- **Nothing changes visually until the owner adds something.** No empty frames or placeholders on public
  pages: every photo section/slot renders nothing when it has no photo.
- **Works on both hosts**: the Codespace live server (persistent disk, SQLite) and **Vercel** (read-only
  filesystem except `/tmp`, Postgres on Supabase through the transaction pooler with emulated prepares,
  encrypted **cookie sessions (4 KB)**, `array` default cache, 4.5 MB request-body limit, no GD).
  Content and photos therefore live **in the database** (photos as base64 text rows) by default.
- **Security first**: auth on every admin route, CSRF everywhere, rate-limited login, strict upload
  validation (raster images only — never SVG/HTML), metadata (GPS) stripped, no raw HTML from the CMS
  except the sanitized Markdown of free pages, CSP unchanged (`script-src 'self'`).
- **Brand**: the admin uses the same tokens, fonts and logo motifs (black canvas, Mondrian accents, triangle)
  but is calm and functional. Mobile-friendly (the owner will upload photos from a phone).

---

## 2. Hosting, configuration & environment

`config/cms.php` (exact keys):

```php
return [
    // Translation groups editable in "Pages & textes", in menu order ⇒ route of the public page to preview (or null).
    'editable_groups' => [
        'home' => 'home', 'about' => 'about', 'community' => 'community', 'services' => 'services.index',
        'gallery' => 'gallery', 'generator' => 'generator', 'motion' => 'motion', 'contact' => 'contact',
        'ui' => 'home', 'components' => 'home', 'photos' => 'gallery', 'pages' => null, 'notices' => null,
        'errors' => null, 'mail' => null,
    ],
    'cache' => [
        'store' => env('CMS_CACHE_STORE'),           // null = the default cache store
        'ttl' => (int) env('CMS_CACHE_TTL', 600),    // seconds a public snapshot is reused
        'retry' => (int) env('CMS_CACHE_RETRY', 30), // seconds before retrying an unreachable database
        'key' => 'cms.snapshot.v1',
    ],
    'media' => [
        'driver' => env('CMS_MEDIA_DRIVER', 'database'), // database | filesystem
        'disk' => env('CMS_MEDIA_DISK', 'media'),        // Laravel disk used by the filesystem driver
        'max_kb' => (int) env('CMS_MEDIA_MAX_KB', 8192), // per uploaded file (Vercel bodies are ≤ 4.5 MB anyway)
        'max_pixels' => 40000000,                       // decompression-bomb guard (width × height)
        'types' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'], // no AVIF (§13 C8)
        'widths' => [480, 960],                         // responsive variants (only those < main width) (§13 C9)
        'max_edge' => 1920,                             // client resizes the main image to this longest edge (§13 C9)
        'quality' => 0.82,                              // client WebP quality (JPEG fallback 0.86)
    ],
    // Fixed photo spots of the pages (labels: admin.slots.{key}); plus dynamic "service.{slug}.cover".
    'slots' => [
        'home.feature' => ['page' => 'home', 'ratio' => '4/3'],
        'about.portrait' => ['page' => 'about', 'ratio' => '4/5'],
        'about.atelier' => ['page' => 'about', 'ratio' => '16/9'],
        'community.feature' => ['page' => 'community', 'ratio' => '16/9'],
        'site.share' => ['page' => 'ui', 'ratio' => '1200/630'],
    ],
    'service_cover_ratio' => '4/3',
    // First-segment URLs a free page may never use (plus every registered route's first segment).
    'reserved_slugs' => ['admin', 'services', 'a-propos', 'communaute', 'galerie', 'atelier-numerique', 'mouvement',
        'contact', 'langue', 'media', 'storage', 'up', 'api', 'css', 'js', 'fonts', 'images', 'generated', 'build',
        'vendor', 'login', 'logout', 'register', 'sitemap', 'robots', 'favicon', 'index'],
    // First administrator: created at the first successful login while the users table is empty.
    'bootstrap_admin' => ['email' => env('ADMIN_EMAIL'), 'password' => env('ADMIN_PASSWORD')],
    'message_statuses' => ['new', 'in_progress', 'done', 'archived'],
    'login_attempts' => 5, // per minute, per e-mail + IP
];
```

- `config/filesystems.php` gains a `media` disk (local, root `storage_path('app/media')`, not public — files
  are always served through `/media/{key}`).
- `config/database.php` pgsql `options` add `PDO::ATTR_TIMEOUT => (int) env('DB_TIMEOUT', 2)` (pdo_pgsql
  connect timeout; default is 30 s) next to the existing emulated-prepares option.
- `api/index.php` defaults (Vercel) add `CMS_CACHE_STORE=file` (→ `/tmp/laravel/storage/framework/cache/data`)
  and `CMS_CACHE_TTL=60`. Nothing else changes there.
- `.env.example` documents `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `CMS_MEDIA_DRIVER`, `CMS_CACHE_STORE`,
  `CMS_CACHE_TTL`, `DB_TIMEOUT`.
- **Vercel production**: DB = Supabase Postgres (schema `laravel`), migrations are applied from the admin
  (**Maintenance → "Mettre à jour la base de données"**, runs `migrate --force`), first admin via
  `ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars. Sessions stay cookie-based: **never flash large input**
  (see §7.3). Codespace: `bin/live.sh` runs migrations at start; `php artisan atelier:admin` creates admins.

---

## 3. Data model (migrations `database/migrations/2026_10_03_0000NN_*.php`, portable SQLite + Postgres)

| Table | Columns |
|---|---|
| `translation_overrides` | `id`; `locale` string(5); `group` string(40); `key` string(190); `value` text; `updated_by` FK users nullable nullOnDelete; timestamps; **unique(locale, group, key)** |
| `media` | `id`; `ulid` string(26) unique (lower-case); `driver` string(20); `mime` string(40); `extension` string(5); `width`, `height`, `size` unsigned int; `original_name` string(255) nullable; `variants` json (list of `{width,height,size,key}`, ascending, main excluded); `alt_fr`, `alt_en` string(300) nullable; `caption_fr`, `caption_en` string(500) nullable; `focal_x`, `focal_y` unsigned tinyint default 50; `service_slug` string(80) nullable index; `in_gallery` bool default false index; `position` unsigned int default 0; `uploaded_by` FK users nullable nullOnDelete; timestamps |
| `media_files` | `id`; `key` string(60) unique; `mime` string(40); `size` unsigned int; `contents` longText (**base64**); `created_at` |
| `media_slots` | `id`; `slot` string(120) unique; `media_id` FK media cascadeOnDelete; `updated_by` FK users nullable nullOnDelete; timestamps |
| `cms_services` | `id`; `slug` string(80) unique; `is_custom` bool default false; `is_published` bool default true; `position` unsigned int nullable; `category`, `accent`, `icon`, `art_style` string(40) nullable; `content` json nullable (`{"fr":{…},"en":{…}}`); `updated_by` FK nullable; timestamps |
| `settings` | `id`; `key` string(120) unique; `value` text nullable; `updated_by` FK nullable; timestamps |
| `custom_pages` | `id`; `slug` string(80) unique; `title_fr` string(120); `title_en` string(120) nullable; `body_fr`, `body_en` text nullable (Markdown); `meta_fr`, `meta_en` string(170) nullable; `cover_media_id` FK media nullable nullOnDelete; `is_published` bool default false; `in_footer` bool default true; `position` unsigned int default 0; `updated_by` FK nullable; timestamps |
| `cms_activity` | `id`; `user_id` FK users nullable nullOnDelete; `action` string(60); `subject` string(160) nullable; `summary` string(255); `created_at` (index) |
| `contact_messages` (alter) | add `status` string(20) default `'new'` index; `notes` text nullable |

Photos are stored base64 because the Supabase pooler runs with emulated prepares (binary strings with NUL
bytes cannot be bound as text). Storage keys: main `{ulid}.{ext}`, variant `{ulid}-{width}.{ext}`.

### Models (`App\Models`, Laravel 13 attribute style like the existing models)
`TranslationOverride`, `Media` (table `media`; casts `variants` array, `in_gallery` bool), `MediaFile`
(no `updated_at`), `MediaSlot`, `CmsService` (casts `content` array, booleans), `Setting`, `CustomPage`,
`CmsActivity` (no `updated_at`), `ContactMessage` (+ `status`, `notes`; `STATUSES` const; `scopeStatus`).
Every CMS model uses the trait `App\Models\Concerns\FlushesCms` (flushes the snapshot on `saved`/`deleted`).
`ContactMessage` keeps working exactly as today for the contact form and `atelier:messages`.

---

## 4. Core services (`App\Cms`, owner: **core**)

### 4.1 `App\Cms\Cms` (container singleton) — the public read model
Loads one **snapshot** (all overrides, CMS services, settings, media metadata, slots, published free pages)
in ≤ 7 queries, cached in `config('cms.cache.store')` under `cms.cache.key` for `cms.cache.ttl` seconds.
On any `Throwable` while loading: log a warning (once per retry window), cache a "failed" marker for
`cms.cache.retry` seconds, and behave as if empty (`available()` = false).

```php
public function available(): bool;                       // snapshot came from the database
public function bypassCache(): void;                     // logged-in admins always read fresh data (also drops the memoized snapshot)
public function flush(): void;                           // forget cached + memoized snapshot (models call it on write)
public function translations(string $locale, string $group): array;    // dot key ⇒ value
public function services(): array;                       // slug ⇒ ['custom'=>bool,'published'=>bool,'position'=>?int,'category'=>?string,'accent'=>?string,'icon'=>?string,'art_style'=>?string,'content'=>['fr'=>array,'en'=>array]]
public function setting(string $key, ?string $default = null): ?string; // row present ⇒ its value (even ''), else $default
public function settings(): array;                       // key ⇒ ?string
public function applySettings(): void;                   // contact.* ⇒ config('atelier.contact.*'), socials.* ⇒ config('atelier.socials.*'); empty notify ⇒ contact email
public function media(int $id): ?MediaItem;              // ANY media row (the snapshot holds the metadata of every photo)
public function gallery(): array;                        // list<MediaItem>: in_gallery, position ASC, id DESC
public function forService(string $slug): array;         // list<MediaItem>: service_slug = $slug, same order
public function slot(string $slot): ?MediaItem;
public function pages(): array;                          // published free pages: list of ['id','slug','title'=>['fr','en'],'body'=>['fr','en'],'meta'=>['fr','en'],'cover'=>?int,'in_footer'=>bool,'position'=>int,'updated_at'=>?string], ordered position, title_fr
public function page(string $slug): ?array;              // one published free page
public function footerPages(): array;                    // pages() where in_footer
```

### 4.2 `App\Cms\MediaItem` (final readonly value object; built from a `Media` model or snapshot array)
Public props: `id, ulid, extension, mime, width, height, variants (list{width,height,key,size}), alt
(['fr'=>?string,'en'=>?string]), caption (same), focalX, focalY, service (?string), inGallery, position,
originalName, updatedAt`. Methods:
`fromModel(Media)`, `fromArray(array)`, `toArray()` (JSON-safe, includes `url`, `thumb` (480 url), `srcset`),
`key(?int $width = null)`, `url(?int $width = null)` (`route('media.show', key)` of the smallest variant
≥ `$width`, main when null/none), `srcset()` (`"…480w, …960w, …, main {width}w"`), `alt(?locale)`
(locale → fr → caption → `''`), `caption(?locale)` (locale → fr → null), `ratio()` (`"{w} / {h}"`),
`objectPosition()` (`"{x}% {y}%"`), `isLandscape()`.

### 4.3 Translation overlay
`App\Cms\OverridingTranslationLoader implements Illuminate\Contracts\Translation\Loader`, registered with
`$this->app->extend('translation.loader', …)`. `load($locale, $group, $namespace)` = the file lines, then for
`$namespace` null/`'*'` and `$group` ∈ `cms.editable_groups`: every override whose key **exists in the file
lines as a string leaf** replaces it (`Arr::set`). Unknown/stale keys are ignored. `files(): Loader` returns
the wrapped file loader (the admin reads defaults through it).

### 4.4 Services overlay (`App\Support\ServiceCatalog`, owner: core)
- Constructor: `new ServiceCatalog(?string $directory = null, ?Cms $cms = null, bool $withHidden = false)`;
  the container singleton passes `app(Cms::class)`. `withHidden(): self` (admin) returns a new instance.
- Files load as today, then each `Cms::services()` row is applied: per locale
  `array_replace_recursive(file[locale], row.content[locale])` restricted to `CONTENT_KEYS`; meta overrides only
  when valid (`category` ∈ config categories, `accent` ∈ config accents, `art_style` ∈ config art_styles,
  `icon` non-empty); `position` ⇒ `order`; `published=false` ⇒ hidden (excluded unless `withHidden`).
- Rows with `custom=true` whose slug is not a file slug become services (`scene` = slug; missing meta ⇒ first
  category / its accent / icon `triangle` / style `pehouet`); invalid ones are skipped with a log line.
- Order by `order` then slug; **`number` = 1-based display position** (equals `order` for the shipped files).
- Localized arrays gain `published` (bool), `custom` (bool), `modified` (bool: a row exists with content or
  meta/position override). New methods: `fileData(string $slug): ?array` (raw file, no overlay),
  `isCustom(string $slug): bool`. `count()`/`slugs()`/`has()` reflect the visible set.
- `PageController@motion` passes only non-custom services as `$scenes`.

### 4.5 Media pipeline (`App\Cms\Media`)
- `MediaStorage` interface: `put(string $key, string $bytes, string $mime): void` (throws on failure),
  `get(string $key): ?array` (`['bytes','mime','size','modified']`), `delete(string $key): void` (missing ok),
  `exists(string $key): bool`, `driver(): string`. Implementations `DatabaseMediaStorage` (`media_files`,
  base64) and `FilesystemMediaStorage` (disk `cms.media.disk`). `MediaStorageManager::driver(?string $name = null)`.
- `MediaManager`:
  `store(UploadedFile $photo, array $variants = [], array $attributes = [], ?int $userId = null): Media`,
  `replace(Media $media, UploadedFile $photo, array $variants = []): Media` (new ulid ⇒ new URLs; keeps id,
  texts, flags and placements; old files deleted after commit), `delete(Media $media): void` (row + files;
  slots cascade, free-page covers null). `$variants` = `[480 => UploadedFile, …]`.
  Validation (throws `App\Cms\Media\InvalidImage` with `->reason` ∈ `not_image|type|too_big|too_many_pixels|storage`):
  upload valid; size ≤ `max_kb`; MIME sniffed with `finfo` ∈ `types` and agreeing with `getimagesize`
  (works without GD); pixels ≤ `max_pixels`. JPEG ⇒ `JpegSanitizer::strip()` (drops APP1 Exif/XMP, APP13, COM;
  keeps APP0/APP2-ICC; **re-inserts a minimal Exif APP1 holding only the Orientation tag** when it was ≠ 1).
  PNG ⇒ `PngSanitizer::strip()` (drops `tEXt`, `iTXt`, `zTXt`, `eXIf`, `tIME`). Variants: key must be in
  `widths` and < main width, actual width within ±2 px, aspect ratio within 2 % of main, same validation;
  invalid variants are skipped. No variants + `extension_loaded('gd')` ⇒ generate them (optional path).
  `attributes` accepted: `alt_fr, alt_en, caption_fr, caption_en, service_slug, in_gallery, position,
  focal_x, focal_y, original_name`.
- `App\Http\Controllers\MediaFileController` — **GET `/media/{key}`** (`media.show`), stateless (no session),
  no throttle (§13 A5), key regex `[0-9a-z]{26}(?:-[0-9]{2,4})?\.(?:jpg|png|webp|gif)`; finds the `Media` by
  ulid, reads through the media's own `driver`; 404 when missing; `ETag: "{key}"` + 304 on `If-None-Match`;
  headers `Content-Type`, `Content-Length`, cache headers of §13 C12,
  `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'`,
  `Content-Disposition: inline; filename="pehouet-{key}"`.

### 4.6 Other core pieces
- `App\Cms\Slots`: `all(): array` (config slots + `service.{slug}.cover` for every service incl. hidden),
  `isValid(string $slot): bool`, `forPage(string $page): array`, `ratio(string $slot): string`,
  `label(string $slot): string` (`admin.slots.{key}`; service covers ⇒ `admin.slots.service_cover` with `:service`).
- `App\Cms\Activity::record(string $action, string $summary, ?string $subject = null): void` — never throws.
- `App\Cms\Placeholders::missing(string $default, string $value): array` — tokens `:name` (regex
  `/(?<![\w:]):([A-Za-z][A-Za-z_]*)/`) and `{name}` present in `$default` but missing in `$value`, plus a
  `'|'` entry when the number of `|` segments differs (pluralised lines).
- `App\Cms\Markdown::render(?string $text): Illuminate\Support\HtmlString` — `Str::markdown($text,
  ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 10])`. The **only** CMS HTML
  output allowed with `{!! !!}`.
- Middleware: `ApplyCms` (appended to the `web` group after `SetLocale`: `if ($request->user()) bypassCache()`,
  then `applySettings()`), `AdminHeaders` (`X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store, private`),
  `EnsureCmsReady` (admin routes except auth & maintenance: when a CMS table is missing ⇒ redirect to
  `admin.maintenance` with a flash). Aliases `admin.headers`, `cms.ready`. Guests ⇒ `admin.login`;
  logged-in users hitting `guest` routes ⇒ `admin.dashboard` (`redirectGuestsTo`/`redirectUsersTo`).
- `php artisan atelier:admin {email} {--name=} {--password=}` — create or update an administrator
  (asks for the password secretly when omitted, min 10 chars).
- `robots.txt` adds `Disallow: /admin`. The sitemap never lists admin URLs.
- Tests helper trait `Tests\Concerns\InteractsWithCms`: `admin(array $attributes = []): User` (creates + acts as),
  `pngFile(string $name = 'photo.png', int $w = 8, int $h = 6): UploadedFile` (valid PNG built with zlib+crc32,
  **no GD**), `jpegFile(…)`, `webpFile(…)` (valid tiny binaries), `uploadPhoto(array $attributes = []): Media`.

---

## 5. Routes (owner of `routes/*`: core; controllers by area)

Public additions (`routes/web.php`): `GET /media/{key}` → `MediaFileController` (`media.show`, stateless);
**admin routes are registered before** the free-page catch-all `GET /{slug}` → `CustomPageController@show`
(`pages.custom`, `where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')`, unknown ⇒ 404 page), which stays the last explicit route
before `Route::fallback`. `/up`, every existing URL and the 404 page must keep working.

Admin (`routes/admin.php`, prefix `admin`, names `admin.*`, middleware `web` + `admin.headers`; all but the two
login routes also `auth`; all but auth & maintenance also `cms.ready`):

| Method & URI | Name | Controller@action |
|---|---|---|
| GET `/admin/connexion` | `admin.login` | `Admin\AuthController@show` (guest) |
| POST `/admin/connexion` | `admin.login.attempt` | `Admin\AuthController@login` (guest) |
| POST `/admin/deconnexion` | `admin.logout` | `Admin\AuthController@logout` |
| GET `/admin` | `admin.dashboard` | `Admin\DashboardController` |
| GET `/admin/textes` | `admin.texts.index` | `Admin\TextController@index` |
| GET `/admin/textes/{group}` | `admin.texts.edit` | `Admin\TextController@edit` |
| PUT `/admin/textes/{group}` | `admin.texts.update` | `Admin\TextController@update` |
| GET `/admin/services` | `admin.services.index` | `Admin\ServiceController@index` |
| POST `/admin/services/ordre` | `admin.services.reorder` | `Admin\ServiceController@reorder` |
| GET `/admin/services/creer` | `admin.services.create` | `Admin\ServiceController@create` |
| POST `/admin/services` | `admin.services.store` | `Admin\ServiceController@store` |
| GET `/admin/services/{slug}` | `admin.services.edit` | `Admin\ServiceController@edit` |
| PUT `/admin/services/{slug}` | `admin.services.update` | `Admin\ServiceController@update` |
| POST `/admin/services/{slug}/visibilite` | `admin.services.toggle` | `Admin\ServiceController@toggle` |
| DELETE `/admin/services/{slug}/personnalisation` | `admin.services.reset` | `Admin\ServiceController@reset` |
| DELETE `/admin/services/{slug}` | `admin.services.destroy` | `Admin\ServiceController@destroy` |
| GET `/admin/photos` | `admin.media.index` | `Admin\MediaController@index` |
| POST `/admin/photos` | `admin.media.store` | `Admin\MediaController@store` |
| GET `/admin/photos/{media}` | `admin.media.edit` | `Admin\MediaController@edit` |
| PUT `/admin/photos/{media}` | `admin.media.update` | `Admin\MediaController@update` |
| POST `/admin/photos/{media}/remplacer` | `admin.media.replace` | `Admin\MediaController@replace` |
| DELETE `/admin/photos/{media}` | `admin.media.destroy` | `Admin\MediaController@destroy` |
| GET `/admin/galerie` | `admin.gallery.index` | `Admin\GalleryController@index` |
| POST `/admin/galerie/ordre` | `admin.gallery.reorder` | `Admin\GalleryController@reorder` |
| POST `/admin/emplacements` | `admin.slots.update` | `Admin\SlotController@update` |
| GET `/admin/pages` | `admin.pages.index` | `Admin\PageController@index` |
| GET `/admin/pages/creer` | `admin.pages.create` | `Admin\PageController@create` |
| POST `/admin/pages` | `admin.pages.store` | `Admin\PageController@store` |
| GET `/admin/pages/{page}` | `admin.pages.edit` | `Admin\PageController@edit` |
| PUT `/admin/pages/{page}` | `admin.pages.update` | `Admin\PageController@update` |
| DELETE `/admin/pages/{page}` | `admin.pages.destroy` | `Admin\PageController@destroy` |
| GET `/admin/messages` | `admin.messages.index` | `Admin\MessageController@index` |
| GET `/admin/messages/{message}` | `admin.messages.show` | `Admin\MessageController@show` |
| PUT `/admin/messages/{message}` | `admin.messages.update` | `Admin\MessageController@update` |
| DELETE `/admin/messages/{message}` | `admin.messages.destroy` | `Admin\MessageController@destroy` |
| GET `/admin/reglages` | `admin.settings.edit` | `Admin\SettingsController@edit` |
| PUT `/admin/reglages` | `admin.settings.update` | `Admin\SettingsController@update` |
| GET `/admin/compte` | `admin.account.edit` | `Admin\AccountController@edit` |
| PUT `/admin/compte` | `admin.account.update` | `Admin\AccountController@update` |
| POST `/admin/compte/administrateurs` | `admin.users.store` | `Admin\AccountController@storeUser` |
| DELETE `/admin/compte/administrateurs/{user}` | `admin.users.destroy` | `Admin\AccountController@destroyUser` |
| GET `/admin/maintenance` | `admin.maintenance` | `Admin\MaintenanceController@index` |
| POST `/admin/maintenance/base` | `admin.maintenance.migrate` | `Admin\MaintenanceController@migrate` |
| POST `/admin/maintenance/cache` | `admin.maintenance.flush` | `Admin\MaintenanceController@flush` |
| GET `/admin/maintenance/export` | `admin.maintenance.export` | `Admin\MaintenanceController@export` |

`{media}`, `{page}`, `{message}`, `{user}` use implicit model binding (ids); `{group}` ∈ editable groups;
`{slug}` = service slug (404 when unknown, hidden ones included).

---

## 6. Admin UI (layout & design system, owner: **admin-ui** then **admin-shell**)

- Layouts: `admin.layouts.app` (sidebar + topbar + flash + content) and `admin.layouts.guest` (login).
  Sections: `title`, `content`; stacks `styles`, `scripts`. Head: `<meta name="robots" content="noindex,nofollow">`,
  `<meta name="csrf-token">`, CSS `01-tokens`, `02-base`, `04-components`, `css/admin/admin.css`, then
  `@stack('styles')`; scripts `head.js` (sync), deferred `core.js`, `js/admin/admin.js`, `@stack('scripts')`.
  Body class `admin` (+ `admin--guest`). Right after `<body>` both layouts `@includeIf('partials.signature-sprite')`
  (the public-site session's shared `#ap-signature` symbol: without it `<x-signature>` and
  `<x-logo-wordmark signature>` render empty). `<html lang>` = current locale. Topbar: page title, "Voir le site"
  (new tab), FR/EN switch (`?lang=`), user name, logout (POST form). Sidebar nav (icon + label + accent):
  Tableau de bord (white), Textes des pages (yellow), Services (red), Photos (blue), Galerie (amber),
  Pages libres (orange), Messages (red, unread badge), Réglages (white), Compte, Maintenance.
  Mobile < 64em: sidebar becomes a drawer toggled by `[data-adm-nav-toggle]` (`html.adm-nav-open`); without JS
  the nav is shown above the content.
- CSS `public/css/admin/admin.css` (prefix `adm-`, tokens only): `.adm-shell .adm-sidebar .adm-nav
  .adm-nav__link(.is-active) .adm-main .adm-topbar .adm-content .adm-page-head .adm-grid(--2|--3|--auto)
  .adm-stack .adm-cluster .adm-card(__head|__body|__foot) .adm-stat .adm-empty .adm-table (cards on mobile)
  .adm-badge(--new|--modified|--hidden|--custom|--success|--danger) .adm-form .adm-fieldset .adm-pair
  (FR | EN columns ≥ 64em) .adm-lang .adm-toggle .adm-savebar (sticky) .adm-counter .adm-default .adm-reset
  .adm-tabs(__list|__tab|__panel) .adm-thumb .adm-media-grid .adm-media-card(.is-selected) .adm-dropzone
  (.is-dragover|.is-busy) .adm-progress .adm-uploads .adm-slot .adm-focal(__dot) .adm-flash(--success|--error|--info)
  .adm-dialog .adm-sortable(__item|.is-dragging) .adm-handle .adm-timeline .adm-pagination`.
  Forms reuse `.field*`, `.btn*`, `.chip`, `.alert*` from `04-components.css`. Admin keyframes are named
  `ap-adm-*` with `@anim` comments (they live in `public/css/admin/`, outside the public animation catalogue).
- Anonymous components `resources/views/components/admin/*.blade.php` (`<x-admin.…>`):
  `page-head` (title, lead=null, back=null; slot = actions), `card` (title=null, accent=null; slot; slot `actions`),
  `field` (name, label, value=null, type='text' (text|email|url|tel|number|password|textarea|select), hint=null,
  rows=3, options=[], maxlength=null, required=false, placeholder=null, lang=null, autosize=true; error from `$errors`),
  `toggle` (name, label, checked=false, hint=null; hidden 0 + checkbox 1), `badge` (variant='default'),
  `empty` (title, text=null, icon='triangle'; slot actions), `stat` (value, label, href=null, accent='yellow', icon=null),
  `thumb` (media: ?MediaItem, size=96, alt=null), `confirm` (action, label, message, method='DELETE',
  variant='danger'), `savebar` (label=null, back=null).
- Shared partials (markup by admin-ui in phase 1, then owned by **admin-media**):
  - `admin.media.partials.slot` — vars `$slot` (key), `$label`, `$media` (?MediaItem), `$ratio`, `$redirect` (URL).
    Shows the current photo (or an empty triangle placeholder), "Choisir / Changer la photo" button
    (`data-media-picker` + `data-slot`, opens the picker) and "Retirer". Without JS: link to
    `admin.media.index?slot={key}&redirect=…` where each photo card has a "Utiliser ici" form. Posts to
    `admin.slots.update` (`slot`, `media_id` (empty = remove), `redirect`).
  - `admin.media.partials.uploader` — vars `$action` (default `route('admin.media.store')`), `$defaults` (hidden
    fields: `service_slug`, `in_gallery`, `slot`…), `$multiple` (bool), `$redirect`, `$label` (optional). Drop zone +
    `<input type="file" accept="image/*" name="photo">` in a real `multipart` form posting to `$action` (works
    without JS: one file); `[data-uploader]` enhances it (§7.6).
  - Sidebar extension: an "Artistes" item rendered only when `Route::has('admin.artists.index')` (see §10).
- JS `public/js/admin/admin.js` (IIFE on `window.AP`): nav drawer, `form[data-confirm]` confirmation,
  `form[data-adm-dirty]` unsaved-changes warning, `[data-adm-tabs]`, `textarea[data-autosize]`,
  `[data-maxlength]` counters, flash dismiss, `[data-adm-filter]` live filter of `[data-adm-filter-item]`,
  and `AP.admin = { csrf(), fetchJSON(url, opts), toast(message, type), openDialog(el), closeDialog(el) }`.
  `public/js/admin/sortable.js`: `[data-sortable]` lists — pointer drag + keyboard (`[data-move="up|down"]`
  buttons), keeps hidden `order[]` inputs in DOM order, optional `data-sortable-autosubmit`.

---

## 7. Admin screens & behaviours

### 7.0 Conventions for every admin controller (and the artists module)
- **Flash messages**: success ⇒ `->with('status', $message)`; failure ⇒ `->with('error', $message)`. The layout
  renders both into `.adm-flash` (`--success` / `--error`); JSON endpoints return `{message}` instead.
- **Invalid forms**: use the trait `App\Http\Controllers\Admin\Concerns\RendersInvalidForms` —
  `return $this->invalid($request, 'admin.x.edit', $viewData, $validator);` — HTTP 422, the view receives
  `$errors` and `old()` works for this request only (the input is never written to the 4 KB cookie session).
  Never `back()->withInput()` on admin forms. Small forms (login) may redirect back with errors only.
- `x-admin.field` value = `old($dotName, $value)` where `$dotName` is the field name converted to dot notation
  (`seo[title]` ⇒ `seo.title`); fields whose keys themselves contain dots (the texts editor) take their values
  from the controller instead.
- Every write records `App\Cms\Activity::record()` and relies on the model's `FlushesCms` trait for the cache.

### 7.1 Auth (admin-ui)
Login: e-mail, password, "Rester connecté"; `RateLimiter` key `admin-login|{email}|{ip}`, `cms.login_attempts`
per minute, lockout message with seconds; session regenerated; `intended` (internal only) or dashboard.
**Bootstrap**: while `users` is empty, credentials equal (hash_equals) to `cms.bootstrap_admin` create that
user then log in. DB errors on the login page ⇒ friendly message, never a 500. Logout invalidates the session
and regenerates the token. `Activity::record('auth.login', …)`.
On Vercel sessions live in an encrypted cookie: logout only forgets the copy of this browser, so a copied
cookie session dies only with a password change (reset included) or "Se déconnecter des autres appareils" (§13 D17).

### 7.2 Dashboard, settings, account, maintenance, messages (admin-shell)
- **Dashboard**: greeting; stats (photos, photos in gallery, modified texts, visible/total services, free pages,
  unread messages); quick actions (Ajouter des photos, Modifier un texte, Nouveau service, Voir les messages);
  last 10 activities; last 5 messages; status card (media driver, DB connection, pending migrations ⇒ link).
  Warn when fewer than 20 services are visible.
- **Settings**: contact `email, phone, whatsapp, address, notify`; socials `instagram, facebook, tiktok, youtube`
  (https URLs); announcement banner `announcement.enabled`, `announcement.fr`, `announcement.en`,
  `announcement.url` (optional internal path or https URL). Saving writes every field (row present ⇒ wins, even
  empty = hidden); "Revenir aux valeurs du fichier .env" deletes the contact/socials rows. The banner is the
  partial `partials/announcement.blade.php` (renders nothing unless enabled and non-empty for the locale, fallback
  fr), styled in `public/css/09-notices.css`, with a dismiss button remembered per text in `localStorage`
  (`public/js/announcement.js`, pushed with `@once @push('scripts')` by the partial). Same owner and stylesheet:
  `partials/preview-banner.blade.php` (var `$preview`, `$label`) shown to a logged-in admin previewing a hidden
  service or an unpublished free page. Strings: `lang/*/notices.php`.
- **Account**: name, e-mail, new password (current password required, min 10, confirmed); administrators list,
  add (name, e-mail, password), delete (never yourself, never the last one).
- **Maintenance**: environment summary (PHP, DB driver, media driver, cache store, session driver), pending
  migrations (via the migrator's repository) + "Mettre à jour la base de données" (POST, `Artisan::call('migrate',
  ['--force' => true])`, output shown), "Vider le cache du contenu" (`Cms::flush()`), "Exporter le contenu"
  (JSON download: translation overrides, cms_services, settings, custom pages, media metadata — never binaries,
  never users/messages).
- **Messages (CRM)**: tabs by status with counts (Nouveaux, En cours, Traités, Archivés, Tous) + unread filter,
  search (`q` in name/email/message), 20 per page with simple prev/next; detail page marks as read, shows
  contact links (mailto with subject, tel, WhatsApp), service link, budget label, language, date; form: status,
  internal notes, "Marquer comme non lu"; delete (confirm). Sidebar badge = unread count.

### 7.3 Texts of the pages (admin-content) — `TextController`
- Index: one card per editable group (label + description from `admin_content.groups.{group}`), number of
  modified texts, "Modifier", "Voir la page" (when the group has a route), and the photo spots of that page.
- Edit: the group's leaves (from the **file** loader) grouped by first-level key (humanized headings); each
  leaf = one row with its path label, the FR and EN fields side by side (input ≤ 120 chars single line, else
  textarea), current value = override or default, a "modifié" badge and the original text when overridden, and a
  "Rétablir l'original" checkbox per locale. Live filter, modified counter, sticky save bar, the page's photo
  spots on top (`admin.media.partials.slot`).
- Input names `t[{locale}][{dot.key}]` and `reset[{locale}][{dot.key}]` (dots inside brackets survive PHP).
  **Validate manually** (Laravel's validator treats dots as nesting): only known string leaves; ≤ 5000 chars;
  `Placeholders::missing()` must be empty; line endings normalised to `\n`. Value equal to the default, empty,
  or reset ⇒ delete the override; otherwise upsert. On errors **re-render the form with status 422** (submitted
  values + an error bag passed to the view) — never `withInput()` (4 KB cookie sessions).

### 7.4 Free pages (admin-content) — `Admin\PageController` + public `CustomPageController`
- Admin list (title, URL, published, in footer, updated) + create/edit: title FR (required) / EN, slug (auto
  from the FR title, editable, unique, `[a-z0-9]+(-[a-z0-9]+)*`, not reserved nor a registered first URL
  segment), body FR/EN (Markdown textarea + short syntax help), meta description FR/EN (≤ 170), cover photo
  (picker), published, in footer, position; delete (confirm).
- Public `/{slug}`: `pages.custom` view (extends `layouts.app`; hero with title + cover photo in a triangle
  frame, `.prose` body from `Markdown::render`, CTA band; its CSS `public/css/pages/custom.css` via
  `@push('styles')`); unpublished/unknown ⇒ 404, except for a logged-in admin (`partials.preview-banner`).
  `partials/footer-pages.blade.php` lists the footer pages under a heading (reusing the footer's existing link
  classes; renders nothing when there are none). Strings: `lang/*/pages.php`. Sitemap lists published free pages
  and custom services (custom services have no content file: handle `path()` = null).

### 7.5 Services (admin-services) — `Admin\ServiceController`
- Index: sortable list (number, icon, title, category, badges Masqué / Créé ici / Modifié, cover thumb, actions:
  modifier, voir, masquer/afficher) + "Ajouter un service"; warning when < 20 visible.
- Edit: meta card (category, accent, icon — radio grid of icons from `App\Support\Icons::names()`, which reads
  the keys of `components/icon.blade.php` — art style, published); cover photo spot (`service.{slug}.cover`);
  "Réalisations" card (photos with `service_slug = slug`, uploader preset with the slug and `in_gallery=1`, link to
  the filtered library); content in FR | EN tabs with every field of the content-file schema
  (title ≤ 60, short ≤ 160, tagline ≤ 140, intro ≤ 1200, body ×2 ≤ 1500, features ×6 {title ≤ 80, text ≤ 400},
  process ×4 {title, text}, ideal_for ×4 ≤ 120, faq ×4 {q ≤ 200, a ≤ 1000}, scene_alt ≤ 300, meta_description ≤ 170).
  File services store only the leaves that differ from the file (blank = original); "Rétablir le contenu
  d'origine" deletes the row. Custom services store everything (FR title, short, tagline, intro required).
- Create: FR title (⇒ slug, unique vs files and rows, editable at creation only), short, tagline, intro, meta.
  Delete only custom services (confirm); hide/show any service.
- Generic scene for services without a scene partial: `resources/views/services/partials/generic-scene.blade.php`
  (vars `$service`, `$cover` ?MediaItem): the cover photo — or the service's art style artwork — framed by the
  logo triangle with drifting Mondrian blocks; root `<div class="scene scene--generic ap-anim-scope" role="img"
  aria-label="{{ $service['scene_alt'] }}">`, fills the `.scene-stage`; CSS `public/css/pages/service-generic.css`
  pushed by the partial itself (`@once @push('styles')`), ≥ 3 keyframes `ap-pg-service-gen-*`
  (`@anim group=page`). Must look complete with motion off.
- Public `ServiceController@show`: hidden services are visible to a logged-in admin with a preview banner
  (`$preview` true); everyone else gets 404.

### 7.6 Photos, gallery & spots (admin-media)
- Library `admin.media.index`: uploader on top; filters (Toutes, Dans la galerie, par service, Sans emploi),
  search (name, alt, caption); grid of cards (thumb, name, dimensions, badges gallery/service/spots, edit).
  `?picker=1` ⇒ only the grid partial (for the picker dialog, each card has "Choisir"); `?slot=…&redirect=…`
  (no-JS flow) ⇒ each card has a "Utiliser ici" form posting to `admin.slots.update`.
- Upload `admin.media.store` (multipart): `photo` (required), `variants[480|960|1600]` (optional), `alt_fr`,
  `alt_en`, `caption_fr`, `caption_en`, `service_slug` (existing service or empty), `in_gallery` (0/1),
  `slot` (optional: assign after upload), `original_name`, `redirect`. JSON when `Accept: application/json`
  (201 `{media: MediaItem::toArray() + edit_url, message}`; 422 `{message, errors}`), else redirect with flash.
  Error messages from `admin_media.errors.{reason}`.
- Client processing `public/js/admin/uploader.js` (`[data-uploader]` on a real `<form>`): for each file
  (multiple, drag & drop, paste, phone camera): decode (`createImageBitmap(file, {imageOrientation:
  'from-image'})`, fallback `<img>`), resize to `max_edge`, encode WebP at `quality` (fallback JPEG when the
  browser cannot encode WebP), build the variants for `widths` smaller than the main width, and upload with
  `XMLHttpRequest` (progress) **to the form's own `action` with `new FormData(form)`** (every hidden field is
  kept; the file input is replaced by the processed `photo` + `variants[w]` + `original_name`), sequentially;
  GIFs are sent untouched; keep each request < 4 MB. Any `201` JSON with a `media` object is a success; the
  form then dispatches `ap:media-uploaded` (bubbles, `detail = {media, response}`) — pages react to it (e.g.
  add a card) instead of reloading when they listen, otherwise the uploader reloads the page at the end.
  Status per file in `.adm-uploads` (`aria-live`). Settings come from `data-*` attributes rendered from
  `config('cms.media')`. This lets other modules (artist artworks) post to their own endpoints.
- Edit `admin.media.edit`: large preview with focal-point picker (`public/js/admin/focal-point.js`, numeric
  inputs fallback), alt FR/EN, caption FR/EN, service, in gallery, position, original name; where it is used
  (gallery, service, spots, free-page covers); "Remplacer la photo" (uploader posting to `admin.media.replace`,
  same client processing); delete (confirm, lists what it removes).
- Gallery `admin.gallery.index`: the gallery photos as a sortable grid (positions via `admin.gallery.reorder`),
  remove-from-gallery toggles, uploader preset `in_gallery=1`.
- Spots `admin.slots.update`: `slot` must satisfy `Slots::isValid`, `media_id` nullable exists; upsert/delete
  `media_slots`; `redirect` honoured only for same-host URLs; JSON or redirect.
- Picker `public/js/admin/media-picker.js`: `<dialog class="adm-dialog">` loading `admin.media.index?picker=1`,
  upload inside the dialog (reuses uploader.js). Two modes:
  **slot mode** (`[data-media-picker][data-slot]` inside the slot form) ⇒ fill that form's `media_id` and submit;
  **field mode** (`[data-media-picker][data-media-target="#input-id"]`) ⇒ set that hidden input to the chosen id,
  dispatch `change` on it, update the `<img>` inside `[data-media-preview]` of the closest `[data-media-field]`
  (and toggle its empty state), no submit. A `[data-media-clear]` button in the same `[data-media-field]` empties
  it. Used by free-page covers and by other modules' forms (artists). Without JS the field shows a `<select>`
  fallback rendered by the page.
- Media usage ("where is this photo used", delete dialog, "Sans emploi" filter) merges the usage reported by
  other modules when their classes exist: `App\Artists\MediaUsage::for(int $mediaId): array` (list of
  `['label' => string, 'url' => string]`) and `App\Artists\MediaUsage::usedIds(): array` (list<int>), guarded by
  `class_exists`.

### 7.7 Public photos (public-photos)

> **Status (orchestrator-maintained): IN PROGRESS — being built by the artists session (atelierpehouet-98) since
> 2026-10-03 14:00 UTC.** Until this line says **DONE**, nobody else edits the files of the public-photos row (§10).
> The CMS phase-2 agent "public-photos" is therefore a **review pass**: if the status is not DONE when it starts, it
> makes NO edits and only reports its findings (the orchestrator relays them); once DONE, it reviews the files against
> this section and §13 F31/A2/A3, fixes real defects with minimal edits and reports them. It never rebuilds them.
- Components: `<x-photo :media sizes="…" :lazy="true" :alt="null" />` (`<img>` with `src` (960 variant),
  `srcset`, `sizes`, `width`/`height`, `alt`, `loading`, `decoding="async"`, `style="object-position: …"`);
  `<x-photo-frame :media :ratio="'4/3'" variant="tri|seam|mondrian" accent="red" :caption="true" lightbox="group" />`
  (artistic frame from the logo: triangle cut corner + accent triangle, white seams, Mondrian block; lightbox link
  to the main image via effects.js `data-lightbox`); `<x-photo-mosaic :items group="…" />` (Mondrian grid with
  white seams, first photo larger).
- `public/css/08-photos.css` (loaded globally by the layout after `07-anim-ui`): ≥ 8 new keyframes `ap-ph-*`
  (`@anim`, group reveal/ui), all used; complete composition with motion off.
- **The public templates belong to the parallel "public site" session.** The CMS never edits an existing public
  view: it ships self-contained partials (each renders nothing when there is no photo, reads its data from
  `cms()`/`MediaItem`, uses only documented primitives `.section .container x-section-heading data-reveal
  data-lightbox data-filter-group`), and the public-site session places one-line includes (§12):
  - `partials/photo-spot.blade.php` (vars `$slot`, `$variant`='tri', `$accent`='yellow', `$caption`=true) — one spot photo;
  - `pages/partials/gallery-photos.blade.php` — "Nos réalisations" section (category filter chips + mosaic + lightbox);
  - `pages/partials/home-photos.blade.php` — `home.feature` + up to 4 recent gallery photos;
  - `pages/partials/community-photos.blade.php` — `community.feature` + photos of community-category services;
  - `services/partials/cover.blade.php` (var `$service`) — cover photo for the intro aside;
  - `services/partials/realisations.blade.php` (var `$service`) — "Réalisations" section of a service page.
  Helpers (core, `app/helpers.php`): `cms(): App\Cms\Cms`, `ap_share_image(?string $slot = 'site.share',
  int $width = 1600): ?string` (absolute URL or null), `ap_service_cover(string $slug): ?App\Cms\MediaItem`.
  Strings: `lang/*/photos.php`.

---

## 8. Internationalisation

New groups (fr + en, identical keys): `admin` (admin-ui ⇒ admin-shell: shell, nav, common actions, auth,
dashboard, settings, account, maintenance, messages, slots labels, activity), `admin_content` (texts editor,
free pages), `admin_services`, `admin_media`; public groups (editable by the CMS itself): `pages` (free pages,
footer heading — admin-content), `notices` (announcement + preview banners — admin-shell), `photos` (photo
sections — public-photos). Never add keys to the public groups owned by the public-site session (`home about
community services ui components contact gallery generator motion errors`).
The admin follows the session locale (`?lang=`) like the site; French is the default.

## 9. Security checklist
Auth + CSRF on every admin route; `X-CSRF-TOKEN` header for XHR; login throttling; session regeneration;
bootstrap only while no user exists; `redirect`/`intended` same-host only; uploads sniffed with `finfo` +
`getimagesize`, SVG/HTML rejected, size/pixel limits, metadata stripped, random keys, served with
`nosniff` + `default-src 'none'`; every CMS string escaped with `{{ }}`; Markdown with HTML stripped and unsafe
links disabled; slugs validated; explicit validated attributes (no mass assignment of request input);
admin `noindex` + `no-store`; no secrets in exports; migrations button POST-only for admins.

## 10. Ownership (phases)

**A parallel Claude session ("public site") is redesigning the public site at the same time and exclusively owns
every EXISTING file under** `resources/views/{layouts,partials,components,pages,services,errors}/**`,
`public/css/**`, `public/js/**` and the public lang groups (`home about community services ui components contact
gallery generator motion errors`). The CMS **never edits those files** — not even one line. The CMS only creates the
NEW files listed below; integration into existing templates is done by that session from the list in §12.

| Agent | Owns |
|---|---|
| **core** (phase 1) | `config/cms.php`, `config/{database,filesystems}.php` edits, `database/migrations/2026_10_03_*`, `app/Models/**` (new + ContactMessage edits), `app/Cms/**`, `app/helpers.php` (new helpers), `app/Support/ServiceCatalog.php`, `app/Http/Middleware/{ApplyCms,AdminHeaders,EnsureCmsReady}.php`, `app/Http/Controllers/MediaFileController.php`, `PageController@motion` (scenes), `SitemapController@robots` (Disallow), `app/Console/Commands/AdminCommand.php`, `app/Providers/AppServiceProvider.php`, `bootstrap/app.php`, `routes/**`, stub controllers for every other new controller, `api/index.php` defaults, `.env.example`, `python/tools/palette_audit.py` (scan `public/js/**`), `tests/Concerns/**`, `tests/Unit/Cms/**`, `tests/Feature/Cms/**`, `tests/Unit/ServiceCatalogTest.php` |
| **admin-ui** (phase 1) | `resources/views/admin/layouts/**`, `admin/partials/**`, `admin/auth/**`, `admin/media/partials/{slot,uploader}.blade.php` (then admin-media), `resources/views/components/admin/**`, `public/css/admin/admin.css`, `public/js/admin/{admin,sortable}.js`, `app/Http/Controllers/Admin/AuthController.php`, `lang/{fr,en}/admin.php`, `tests/Feature/Admin/AuthTest.php` |
| **admin-shell** (phase 2) | everything of admin-ui above except the media partials + `Admin/{Dashboard,Settings,Account,Maintenance,Message}Controller.php`, views `admin/{dashboard,settings,account,maintenance,messages}/**`, `partials/{announcement,preview-banner}.blade.php`, `public/css/09-notices.css`, `public/js/announcement.js`, `lang/{fr,en}/notices.php`, tests `tests/Feature/Admin/{Dashboard,Settings,Account,Maintenance,Message}Test.php`, `tests/Feature/Cms/NoticesTest.php` |
| **admin-content** (phase 2) | `Admin/{TextController,PageController}.php`, `app/Http/Controllers/CustomPageController.php`, `SitemapController@index`, views `admin/texts/**`, `admin/pages/**`, `pages/custom.blade.php`, `partials/footer-pages.blade.php`, `public/css/admin/content.css`, `public/css/pages/custom.css`, `public/js/admin/text-editor.js`, `lang/{fr,en}/{admin_content,pages}.php`, tests `tests/Feature/Admin/{Text,Page}Test.php`, `tests/Feature/CustomPageTest.php` |
| **admin-services** (phase 2) | `Admin/ServiceController.php`, public `ServiceController@show` (`$preview`), `app/Support/Icons.php`, views `admin/services/**`, `services/partials/generic-scene.blade.php`, `public/css/admin/services.css`, `public/css/pages/service-generic.css`, `public/js/admin/service-editor.js`, `lang/{fr,en}/admin_services.php`, tests `tests/Feature/Admin/ServiceTest.php` |
| **admin-media** (phase 2) | `Admin/{MediaController,GalleryController,SlotController}.php`, views `admin/media/**`, `admin/gallery/**`, `public/css/admin/media.css`, `public/js/admin/{uploader,media-picker,focal-point}.js`, `lang/{fr,en}/admin_media.php`, tests `tests/Feature/Admin/{Media,Gallery,Slot}Test.php` |
| **public-photos** (phase 2) — **BUILT BY THE ARTISTS SESSION (atelierpehouet-98), see §7.7 status** | `resources/views/components/{photo,photo-frame,photo-mosaic}.blade.php`, `partials/photo-spot.blade.php`, `pages/partials/{gallery,home,community}-photos.blade.php`, `services/partials/{cover,realisations}.blade.php`, `public/css/08-photos.css`, `lang/{fr,en}/photos.php`, `tests/Feature/PublicPhotosTest.php` — the CMS "public-photos" agent only REVIEWS these files (see §7.7 status) |

**A third session ("artists", contract `docs/ARTISTS.md`) builds artist pages on top of this CMS with NEW files
only**: `database/migrations/2026_10_03_100000_create_artists_tables.php`, `app/Models/{Artist,Artwork,Exhibition}.php`,
`app/Artists/**`, `app/Http/Controllers/ArtistController.php`, `app/Http/Controllers/Admin/{Artist,Artwork,Exhibition}Controller.php`,
`routes/{artists,admin-artists}.php` (already required, guarded by `is_file`, from `routes/web.php` before the catch-all and
inside the `auth`+`cms.ready` group of `routes/admin.php`), `resources/views/artists/**`, `resources/views/admin/artists/**`,
`public/css/pages/artists.css`, `public/css/admin/artists.css`, `public/js/admin/artists.js`,
`lang/{fr,en}/{artists,admin_artists}.php`, `tests/Feature/Artists/**`. Never edit those. Hooks the CMS provides for it
(all guarded so they work before/without it): sidebar item "Artistes" (`Route::has('admin.artists.index')`,
label `__('admin_artists.nav')`, active on `admin.artists.*`, icon `palette`, accent orange) — admin-ui/admin-shell;
generic uploader & picker field mode (§7.6) — admin-media; media usage merge (§7.6) — admin-media; sitemap merge of
`App\Artists\ArtistDirectory::sitemapEntries()` (url ⇒ last-modified timestamp|null, same shape as the sitemap's
`$pages`) — admin-content; `artists` added to the editable text groups when `lang/fr/artists.php` exists (the text
editor must skip groups whose file or route is missing) — admin-content; dashboard stat "Artistes" and export of the
tables `artists`, `artworks`, `exhibitions` when they exist (`Schema::hasTable`) — admin-shell. Artist photos are
`Media` rows created through `MediaManager::store()` and rendered with `cms()->media($id)`.

Shared rules for every agent: read `docs/ARCHITECTURE.md` and this file first; write only what you own (small
additive edits to other CMS-owned files only when unavoidable, and say so in your report); `php artisan test`
must stay green; run `XDEBUG_MODE=off vendor/bin/pint <your php files>`; never commit, push, stop the live
server, edit `bin/`, or touch the public-site session's files.

## 12. Integration includes (applied by the public-site session, wherever they fit its design)

| File | Include |
|---|---|
| `layouts/app.blade.php` | add `'08-photos', '09-notices'` after `'07-anim-ui'` in the sheet list; `$ogImage = $sectionText('og_image') ?: ap_share_image() ?: asset(…)`; `@include('partials.announcement')` right after the skip link |
| `partials/footer.blade.php` | `@include('partials.footer-pages')` in the links area |
| `services/show.blade.php` | replace the `scene--fallback` div by `@include('services.partials.generic-scene', ['service' => $service])`; `@include('partials.preview-banner', ['preview' => $preview ?? false, 'label' => __('notices.preview.service')])` at the top of the article; `@include('services.partials.cover', ['service' => $service])` in the intro aside; `@include('services.partials.realisations', ['service' => $service])` between process and inspirations; `@section('og_image', ap_service_cover($service['slug'])?->url(1600) ?? '')` |
| `pages/gallery.blade.php` | `@include('pages.partials.gallery-photos')` before the generative gallery |
| `pages/home.blade.php` | `@include('pages.partials.home-photos')` near the gallery/community teasers |
| `pages/about.blade.php` | `@include('partials.photo-spot', ['slot' => 'about.portrait', 'accent' => 'blue'])` and `['slot' => 'about.atelier', 'variant' => 'mondrian']` |
| `pages/community.blade.php` | `@include('pages.partials.community-photos')` |

## 13. Amendments after the design review (BINDING — they override earlier sections where they differ)

Owner tags: **[core-h]** = phase-1.5 "core hardening" agent (core's files + `ContactController`,
`app/Http/Controllers/Admin/Concerns/**`, `config/auth.php`, `api/php.ini`, a new `tests/Integration/**`);
**[auth-h]** = phase-1.5 "auth hardening" agent (admin-ui's files + `Admin\PasswordController`);
**[media] [content] [services] [shell] [photos]** = the phase-2 agents; **[orch]** = the orchestrator.

**A. Database outages, snapshot, cache** [core-h]
1. `App\Cms\DatabaseHealth` circuit breaker (container singleton) — exact API: `available(): bool` (false while the
   breaker is open), `failed(Throwable $e): void` (opens it), `attempt(callable $callback, mixed $fallback = null): mixed`
   (runs the callback unless open; on any Throwable calls `failed()` and returns `$fallback`). The first connection
   failure in a request sets an in-request flag and a
   cached marker (`cms.cache.retry`, default 120 s on Vercel via `api/index.php`); every DB touchpoint on public routes
   (snapshot load, ApplyCms, `ContactController::persist`, `Activity::record`, free-page/preview lookups) checks it
   first and skips the DB. `DB_TIMEOUT` default **2** (libpq applies it per resolved address: worst case = timeout ×
   addresses). `.env.example` documents `ALTER ROLE … SET statement_timeout = '5s'` and `SET search_path TO laravel`.
2. The cached snapshot (and the failed marker) contain **only arrays/strings/ints/bools/null** (dates ISO strings,
   media as arrays rebuilt with `MediaItem::fromArray()`), carry a `version` key, and anything else read back is a
   miss (`cache.serializable_classes` stays false). Free-page **bodies are not in the snapshot**: `page($slug)` loads
   one page on demand (cached under its own key, same TTL/fallback rules). Test with a serializing store.
3. **Stale-if-error**: every successful load also stores `{key}.lastgood` (no TTL); a failed refresh serves it; only
   with no copy at all does the CMS behave as empty. When `!Cms::available()` and nothing is cached, CMS-only URLs
   (`/{slug}`, an unknown `/services/{slug}`) and `sitemap.xml` answer **503** + `Retry-After: 300` +
   `Cache-Control: no-store` instead of 404; admin pages hit by a `QueryException`/`PDOException` render a friendly
   French 503 admin page (how to wake a paused Supabase project).
4. **Flush**: `FlushesCms` only on `TranslationOverride, Media, MediaSlot, CmsService, Setting, CustomPage`; every
   admin write path — including query-builder upserts/updates/deletes, reorders, settings reset, migrations — calls
   `cms()->flush()` (via `DB::afterCommit` inside transactions). `flush()` never throws and also resets in-process
   memos (`app('translator')->setLoaded([])`, `app()->forgetInstance(ServiceCatalog::class)`). Tests: a guest GET right
   after each admin write (same persistent cache store) shows the change.
5. `ApplyCms`: the admin bypass is decided **without the DB**: `$request->hasSession() &&
   $request->session()->has(Auth::guard('web')->getName())`, in a try/catch; it never calls `$request->user()`.
   Stateless routes (`media.show`, `generator.art`, `sitemap`, `robots`) are registered with
   `->withoutMiddleware([...$stateless, ApplyCms::class])`; `/media` has **no throttle** (CDN-fronted).
6. `ContactController` (now core's): e-mail first, then persist only when the breaker is closed; the last-good
   snapshot keeps the owner's notify address during outages. Test: DB unreachable ⇒ success redirect + mail sent.

**B. Postgres rules** [core-h, every agent]
7. Booleans are written and queried only with PHP bools (`$request->boolean()`, `true/false`) — never 0/1; every CMS
   (and artists) model boolean has a bool-normalising mutator. Every string is `mb_scrub`bed, stripped of `"\0"` and cut
   to its column size before insert (`original_name`, activity `summary`/`subject`…). Text search uses
   `whereLike($col, '%'.$escaped.'%', caseSensitive: false)` with `%`/`_` escaped. Best-effort writes
   (`Activity::record`) never run inside a transaction and never swallow an exception inside `DB::transaction`.

**C. Media** [core-h unless tagged]
8. Sanitizers are **allow-lists that rebuild the file**; any structural error ⇒ `not_image`. JPEG keeps SOI, APP0
   JFIF/JFXX, APP2 `ICC_PROFILE` only, APP14 Adobe, DQT/DHT/DRI/SOFn and every SOS scan up to the first EOI; drops every
   other APPn, COM and everything after EOI; re-adds the Orientation-only APP1. PNG keeps IHDR PLTE IDAT IEND tRNS gAMA
   cHRM sRGB iCCP sBIT cICP pHYs bKGD acTL fcTL fdAT, drops the rest and anything after IEND. `WebpSanitizer` drops
   EXIF/XMP chunks, clears the VP8X flags and fixes the RIFF size; `GifSanitizer` drops comments and application
   extensions other than NETSCAPE2.0/ANIMEXTS1.0 and anything after the trailer. **AVIF is not accepted.** When the
   kept Orientation is 5–8, stored width/height are swapped. Fixtures: GPS in Exif/XMP for each format, a JPEG with an
   MPF image and an appended MP4, a PNG with data after IEND.
9. Footprint: `max_edge` **1920**, `widths` **[480, 960]** (the main image serves wide screens and the lightbox);
   `cms.media.quota_mb` (env `CMS_MEDIA_QUOTA_MB`, default 300 for the database driver) enforced in store/replace
   (reason `quota`); stored files are capped at 3.5 MB (Vercel responses ≤ 4.5 MB).
10. Effective upload limit = min(`max_kb`·1024, `upload_max_filesize`, `post_max_size` − 256 KB, 4 000 000 when the
    `VERCEL` env var is set) — `MediaManager::maxUploadBytes()`; rendered into the uploader's `data-*` [media].
    `UPLOAD_ERR_INI_SIZE/FORM_SIZE` ⇒ `too_big`; `UPLOAD_ERR_PARTIAL/NO_FILE` ⇒ new reason `interrupted`;
    `PostTooLargeException` on `admin/*` ⇒ French JSON 413 `{message}` (or redirect back with `error`).
    `api/php.ini`: `upload_max_filesize = 5M`, `post_max_size = 6M`. `bin/live.sh` passes
    `-d upload_max_filesize=10M -d post_max_size=12M` [orch].
11. Server-side variants only when `cms.media.server_variants` (default false) and GD exist; never for GIFs.
    (Vercel's PHP runtime does load GD — §1's "no GD" is about the Codespace.)
12. `/media/{key}`: `Cache-Control: public, max-age=31536000, immutable` + `Vercel-CDN-Cache-Control: public,
    max-age=86400` (edge copies refresh daily); unknown keys 404 with `Cache-Control: no-store`. Replace/delete keep
    the retired keys servable for 2 × `cms.cache.ttl` (min 120 s) through a `media_retired` table (key, driver,
    retired_at), purged on later writes; dialogs say old copies may stay reachable up to 24 h and that only
    "Supprimer" removes a file.
13. Uploader [media]: WebP only when `blob.type === 'image/webp'`, else JPEG 0.86 (variants too, real type and
    extension); decode one file at a time and `close()` each ImageBitmap; HEIC/HEIF the browser cannot decode ⇒
    `admin_media.errors.heic` (French help: iPhone *Réglages › Appareil photo › Formats › « Le plus compatible »*);
    never upload an undecodable original; refuse requests/GIFs above the effective limit before sending; map HTTP
    413 / status 0 / timeout to French messages; "Réessayer" per file; XHR timeout; `beforeunload` guard while
    uploading. Library and picker paginated (48 per page). Admin JS writes untrusted strings only with
    `textContent`/`setAttribute` (innerHTML only for the server-rendered picker partial).
14. Library uploader destination choice [media]: "Dans la galerie" (default, `in_gallery=1`), "Pour un service"
    (select ⇒ `service_slug` + gallery), "Seulement dans la bibliothèque"; the success message says where the photos
    now appear with a "Voir sur le site" link; unused photos get the badge "Visible nulle part sur le site".

**D. Auth & accounts**
15. Login limiter [auth-h]: `new \Illuminate\Cache\RateLimiter(Cache::store(config('cms.login_limiter_store')))`
    (env `CMS_LOGIN_LIMITER_STORE`, default `database` = the existing `cache` table) used only by AuthController,
    inside the same try/catch as the credential check (limiter failure ⇒ friendly "réessayez plus tard"); key on
    `Str::lower(trim($email))`; buckets email|ip 5/min, email 20/hour, ip 50/hour, all checked before the bootstrap
    comparison. Never set `CACHE_LIMITER` globally. Test: with `CACHE_STORE=array` the 6th attempt is refused.
16. Bootstrap [auth-h]: active only when both env values are non-empty strings and the password has ≥ 10 characters
    (else disabled + one log warning); e-mails compared lower-cased/trimmed, password with `hash_equals` on strings;
    users-empty re-checked in a transaction (unique index stops a duplicate); **if the `users` table is missing and the
    credentials match, run the migrations (same code path as Maintenance) before creating the user**; after a
    bootstrap login flash "vous pouvez retirer ADMIN_PASSWORD des réglages Vercel".
17. Sessions [core-h for config/routes, auth-h for UI]: `auth.session` (`AuthenticateSession`) on every authenticated
    admin route; `config/auth.php` guard `web` `'remember' => 43200` (30 days); Compte offers "Se déconnecter des
    autres appareils" (`Auth::logoutOtherDevices($currentPassword)`) [shell]; §7.1 notes that on Vercel a copied
    cookie session dies only with a password change or that button.
18. Explicit admin right [core-h]: migration adds `users.is_admin` (default false) and **sets it to true for every
    existing user**; bootstrap, `atelier:admin` and "add administrator" set it; `Gate::define('admin', …)`;
    `can:admin` on the admin group after `auth` (login routes excluded); test: a non-admin user gets 403. **While the
    column does not exist yet (migrations pending — e.g. production's existing user #1 before the first update), the
    gate allows the user** (`! array_key_exists('is_admin', $user->getAttributes()) || $user->is_admin === true`), so
    the owner can reach Maintenance and run the update; never assume an empty `users` table in production.
19. Account [shell]: current password required for an e-mail change, adding and deleting an administrator; all three
    logged with the acting user. Password reset by e-mail [auth-h]: "Mot de passe oublié ?" through Laravel's
    password broker (`password_reset_tokens`) when a real mailer is configured, else the login page says to ask
    another administrator; Compte lets an admin set a new password for another admin [shell].
    Routes (guest): GET/POST `/admin/mot-de-passe-oublie` (`admin.password.request` / `admin.password.email`),
    GET/POST `/admin/reinitialiser/{token}` (`admin.password.reset` / `admin.password.update`) →
    `Admin\PasswordController`.
20. Session expiry [auth-h + core-h route]: GET `/admin/jeton` (`admin.token`, auth, no-store) ⇒ `{token}`;
    admin.js refreshes `input[name=_token]` + the csrf meta when the page becomes visible again, every 10 min while a
    `form[data-adm-dirty]` is dirty and before each submit/XHR (one retry on 419); dirty forms are copied to
    `sessionStorage` (keyed by action) on submit and restored when the same form renders without a success flash.
    A 419 on `admin/*` renders `admin.errors.expired` (French: "Votre session a expiré : reconnectez-vous, vos
    modifications vous attendent").

**E. Forms & URLs** [core-h for the shared pieces, every agent for its forms]
21. Admin HTML forms never use `$request->validate()`, `validate()`, `ValidatesRequests` or FormRequest: use
    `Validator::make()` + `$this->invalid()` (status 422). JSON/XHR endpoints may. Each admin form test asserts 422
    (not 302) on invalid input; one test per module runs with `SESSION_DRIVER=cookie` and asserts every `Set-Cookie`
    stays < 4096 bytes. Safety net in `bootstrap/app.php` `dontFlash`: `t, reset, content, body_fr, body_en, meta_fr,
    meta_en, alt_fr, alt_en, caption_fr, caption_en, notes, announcement` + the artists module's `bio_fr, bio_en,
    statement_fr, statement_en, description_fr, description_en`. Free-page bodies ≤ 20 000 chars; every
    field has a max. `invalid()` also `View::share('errors', $bag)` so anonymous components see the errors [orch, done].
22. `App\Cms\SafeUrl`: `internal(?string $url, string $fallback)` accepts only a path starting with exactly one `/`
    (next char neither `/` nor `\`), no backslash/whitespace/control char ⇒ `url($path)`, else the fallback;
    `external(?string)` requires `https`, `FILTER_VALIDATE_URL`, no backslash/whitespace. Used for every `redirect`
    field/query, `url.intended` (pulled and validated, never `redirect()->intended()` blindly), internal announcement
    links; `external()` for socials and external announcement links — on save AND again in `applySettings()`.
    Tests: `//evil.com`, `/\evil.com`, `\\evil.com`, a tab before `//evil.com`, `https://evil.com\@host/`,
    `javascript://host/%0aalert(1)`.
23. `EnsureCmsReady` = one query: every `database/migrations/2026_10_03_*` name (the artists migration included, on
    purpose) is in the `migrations` table
    (memoized), `QueryException` ⇒ redirect to maintenance; it runs **before** `SubstituteBindings`
    (`prependToPriorityList`). The migrate button runs under a `Cache::store('database')->lock('cms-migrate', 120)`.
24. CRM links [shell]: `tel:` + digits/plus only; `https://wa.me/` + digits; `mailto:` only when the address contains
    none of `? & # %` or quotes (else plain text), subject/body `rawurlencode`d; message body `{{ }}` +
    `white-space: pre-line`. Bulk "Archiver"/"Supprimer" with checkboxes; "Copier l'adresse/le numéro" buttons.
    Settings "Alertes e-mail" card: status (warning when the mailer is `log`/`array` or the from-address is a
    placeholder) + throttled "M'envoyer un e-mail de test"; same warning on the dashboard.

**F. Owner experience**
25. **Canonical admin** [auth-h]: `cms.primary_url` (env `CMS_PRIMARY_URL`, default
    `https://atelierpehouet.vercel.app`). When `url('/')` differs, both admin layouts show a permanent French warning
    ("Copie de test : les modifications faites ici n'apparaissent pas sur le site officiel") linking to
    `{primary}/admin`. The two hosts share no data (Codespace = SQLite, Vercel = Supabase).
26. Custom services [services]: created **hidden**, redirect to the edit screen with a preview link and "Ce service
    n'est pas encore visible sur le site"; `toggle` refuses to publish (French list of what is missing) until FR has
    title, short, tagline, intro, ≥ 1 body paragraph, ≥ 3 features, ≥ 3 process steps, ≥ 1 ideal_for. Each list item
    has "Masquer cet élément" (stored as `null`); `ServiceCatalog` [core-h] drops null/blank list items (feature/process
    without title, FAQ without q+a, empty strings). The generic-scene partial starts `@php($cover ??=
    ap_service_cover($service['slug']))`. Public preview of a hidden service uses `withHidden()` for find, neighbors and
    related [services]. `Icons::names()` falls back to the ARCHITECTURE §8.2 list; icons are validated against it.
27. Texts editor [content]: the live filter matches FR/EN values and file defaults (case- and accent-insensitive);
    a row's label is `admin_content.labels.{group}.{key}` when it exists, else the current French text (truncated)
    with the key as a small hint; sections from `admin_content.sections.{group}.{key}` else humanized; `meta` rows say
    "Description pour Google (160 caractères max.)"; placeholder errors name the token and its meaning; every admin
    validator uses French attribute names (`admin*.attributes`); under the save bar: "Un champ vidé reprend le texte
    d'origine".
28. Free pages [content]: "Insérer une photo" (picker field mode) inserts `![alt](/media/{key})` at the cursor;
    `Markdown::render` [core-h] keeps `<img>` only for same-origin `/media/` URLs (others become links) and adds
    `loading="lazy"`; a migration [core-h] inserts two **unpublished** pages "Mentions légales" (`mentions-legales`) and
    "Politique de confidentialité" (`confidentialite`) with FR/EN skeleton headings when the slugs are absent.
29. Admin bar [shell]: new public partial `partials/admin-bar.blade.php` (renders nothing for guests; uses the
    session check of A5, no DB) with "Modifier les textes de cette page" (reverse map route ⇒ group from
    `cms.editable_groups`), "Modifier ce service", "Modifier cette page", "Photos de cette page"; add it to §12.
    Generic hook: when the page defines `@section('admin_edit_url', …)` (and optionally `admin_edit_label`), the bar
    shows that link (used by the artists module).
30. Versions [core-h columns, shell UI]: `cms_activity` gains nullable `before`/`after` JSON (texts, services, free
    pages, settings, photo metadata); the activity list offers "Restaurer cette version" on the last 50 entries.
    Maintenance shows technical details in a collapsed section; while migrations are pending the dashboard shows a
    banner with the update button. The JSON export is labelled "Exporter les textes et réglages (pour un
    développeur)".
31. Every slot partial renders `id="spot-{slot-with-dashes}"` and the admin slot card has a "Voir sur la page" link
    [photos, media]. Phase-2 agents never use components another phase-2 agent creates (render photos with
    `MediaItem::url()/srcset()` directly).
32. Release gate [core-h]: `tests/Integration/PublicIntegrationTest.php` (`#[Group('integration')]`, excluded from the
    default run in `phpunit.xml`, run with `php artisan test --group=integration`): assigns a photo to every slot, a
    gallery photo, a service photo + cover, a published footer page and an enabled banner, then asserts the public pages
    contain the matching `/media/` URLs, the footer link, the banner text and `og:image`. The CMS is not announced as
    finished until it passes.
33. `resources/content/animations.json`: any agent that adds/renames keyframes in `public/css/{*,pages/*,scenes/*}.css`
    runs `python3 python/tools/animations.py` as its last step; a failing animation test caused only by another
    agent's in-progress keyframes is not yours to fix [orch re-runs it after each phase].
34. Contract intro exclusions (not editable): the 257 animation names, the generated artwork titles, form validation
    messages, the brand name (`config('atelier.name')`); built-in pages can't be hidden.

**G. Light theme (added 2026-10-03)**
35. The public site gets a light theme (`html[data-theme="light"]`, contract `docs/THEME.md`, built by the artists
    session). Every PUBLIC stylesheet or partial the CMS writes (`pages/custom.css`, `09-notices.css`,
    `pages/service-generic.css`, the announcement / preview-banner / admin-bar / footer-pages partials) styles surfaces
    and text with the semantic tokens of `docs/THEME.md` (`--ap-bg`, `--ap-surface`, `--ap-text`, `--ap-text-soft`,
    `--ap-border`, `--ap-fg-NN`) — never raw `--ap-black`/`--ap-white` for backgrounds or text — so both themes work;
    check both themes in screenshots. **The admin stays dark**: both admin layouts render
    `<html … data-theme="dark" data-theme-lock>` (done) and the public theme script must never change `data-theme`
    when `data-theme-lock` is present; admin CSS keeps its own palette.

## 11. Verification
```bash
XDEBUG_MODE=off php bin/blade-lint.php resources/views
XDEBUG_MODE=off php artisan test
cd python && python3 -m unittest discover -s tests -t . && cd ..
python3 python/tools/animations.py --check
python3 python/tools/palette_audit.py
XDEBUG_MODE=off vendor/bin/pint --test
```
