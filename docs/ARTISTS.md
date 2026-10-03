# Ateliers Pehouet — Artist pages contract

> Binding contract for the **artists** module: public artist pages (biography, artworks, exhibitions) that the
> owner creates and edits in the admin CMS, with photos from the CMS photo library. It extends
> `docs/ARCHITECTURE.md` and `docs/CMS.md` — their rules still apply (logo palette only, `ap-` keyframes with
> `@anim` comments, no inline `<script>`/`on*=`, all copy through `__()` with identical fr/en keys, French first,
> CMS §1 principles, CMS §7.0 admin conventions). When code and this document disagree, fix the code or update
> this document deliberately.

User request (2026-10-03): *"Allow the CRM to create artist pages — biographie and some artwork and expo — but design
one as references more professional, and allow the CRM to edit and add image."* Hence:

1. The owner creates, edits, orders, publishes/hides and deletes **artist pages** at `/admin/artistes`.
2. Each artist page has a **biography** (statement + Markdown bio), **artworks** (photo, title, year, technique,
   dimensions, availability, description) and **exhibitions** (solo/group/residency/fair…, venue, city, dates).
3. **Images**: portrait, artwork photos and exhibition visuals are CMS `Media` rows — uploaded (from a computer or a
   phone, several at once for artworks), chosen from the library, replaced or removed from the artist screens.
4. **One professionally designed reference**: the public artist page is a gallery-grade template, and a button
   creates a complete **example artist** (fictional, unpublished, flagged "Exemple") so the owner sees a finished
   page and edits or deletes it.

---

## 1. Principles

- **Never break the public site.** If the database is unreachable/paused, slow, or the artist tables are missing,
  `/artistes` renders a calm empty state (HTTP 200) and `/artistes/{slug}` a 404 — never a 500, never a hang longer
  than the DB connect timeout; a failed load is negatively cached (same store/TTL/retry as `App\Cms\Cms`).
- **Works before/without the CMS core.** Every call into `App\Cms\*` / `cms()` / `App\Models\Media` is guarded
  (`function_exists('cms')`, `class_exists(...)`) so the module degrades to "no photos" instead of failing.
- **Photos = the CMS library.** No second upload pipeline: `App\Cms\Media\MediaManager::store()/replace()/delete()`
  write, `cms()->media($id)` (→ `App\Cms\MediaItem`) reads. Artworks are **never cropped** (natural aspect ratio).
- **Same hosting constraints as the CMS**: Vercel read-only FS, 4 KB cookie sessions (re-render invalid forms with
  422 through `RendersInvalidForms`, never `withInput()`), ≤ 4.5 MB request bodies (client-side resize by the
  CMS uploader), no GD, Postgres (Supabase pooler) and SQLite.
- **Security**: auth + CSRF on every admin route (they live in the CMS `auth`+`cms.ready` group), explicit validated
  attributes (no mass assignment of request input), every string escaped, only `App\Cms\Markdown::render()` output
  with `{!! !!}`, `https` URLs only for external links, same-host redirects only.
- **Professional tone**: the public artist page is calmer and more editorial than the service pages — gallery
  conventions (titles in italics, technique and dimensions, CV of exhibitions), generous black space, the logo
  motifs used with restraint.

## 2. Ownership

Session **artists** (atelierpehouet-ce) owns exactly these NEW files (recorded in `docs/CMS.md` §10):

| Area | Files |
|---|---|
| backend | `database/migrations/2026_10_03_100000_create_artists_tables.php`, `app/Models/{Artist,Artwork,Exhibition}.php`, `app/Artists/**`, `app/Http/Controllers/ArtistController.php`, `app/Http/Controllers/Admin/{ArtistController,ArtworkController,ExhibitionController}.php`, `routes/artists.php`, `routes/admin-artists.php`, `tests/Feature/Artists/**` |
| public | `resources/views/artists/**`, `public/css/pages/artists.css`, `lang/{fr,en}/artists.php` |
| admin | `resources/views/admin/artists/**`, `public/css/admin/artists.css`, `public/js/admin/artists.js`, `lang/{fr,en}/admin_artists.php` |
| example | `resources/content/artists/example.php`, `resources/content/artists/example/*.webp` |
| docs | `docs/ARTISTS.md` |

Never edit files of the CMS session (`routes/web.php`, `routes/admin.php`, `app/Cms/**`, `app/Http/Middleware/**`,
`bootstrap/**`, `config/**`, admin layouts/partials/components, other models, `tests/Concerns/**`…) or of the public-site
session (every existing file under `resources/views/{layouts,partials,components,pages,services,errors}`,
`public/css/**`, `public/js/**`, the public lang groups). Hooks those sessions provide: see §9.

---

## 3. Data model — `database/migrations/2026_10_03_100000_create_artists_tables.php`

Runs after the CMS migrations (`2026_10_03_0000NN_*`, which create `media`). Portable SQLite + Postgres. `down()` drops
`exhibitions`, `artworks`, `artists`.

| Table | Columns |
|---|---|
| `artists` | `id`; `slug` string(80) **unique**; `name` string(120); `discipline_fr`, `discipline_en` string(120) nullable; `location` string(120) nullable; `statement_fr`, `statement_en` string(400) nullable; `bio_fr`, `bio_en` text nullable (Markdown); `meta_fr`, `meta_en` string(170) nullable; `portrait_media_id` FK `media` nullable **nullOnDelete**; `accent` string(10) default `'yellow'`; `website`, `instagram` string(255) nullable; `is_published` bool default false index; `is_example` bool default false; `position` unsigned int default 0; `updated_by` FK `users` nullable nullOnDelete; timestamps |
| `artworks` | `id`; `artist_id` FK `artists` **cascadeOnDelete**; `media_id` FK `media` nullable **nullOnDelete**; `title_fr` string(160); `title_en` string(160) nullable; `year` string(20) nullable; `medium_fr`, `medium_en` string(160) nullable; `dimensions` string(80) nullable; `description_fr`, `description_en` text nullable; `availability` string(20) default `'none'`; `is_published` bool default true; `position` unsigned int default 0; timestamps; index(`artist_id`, `position`) |
| `exhibitions` | `id`; `artist_id` FK `artists` **cascadeOnDelete**; `media_id` FK `media` nullable **nullOnDelete**; `title_fr` string(160); `title_en` string(160) nullable; `kind` string(20) default `'group'`; `venue` string(160) nullable; `city` string(120) nullable; `year` unsigned smallint; `starts_on`, `ends_on` date nullable; `description_fr`, `description_en` text nullable; `url` string(255) nullable; `is_published` bool default true; timestamps; index(`artist_id`, `year`) |

### Models (`App\Models`, Laravel 13 attribute style like `ContactMessage`)
- `Artist` — `#[Fillable]` = every column above except `id`/timestamps; casts `is_published`, `is_example` bool,
  `position` int. Relations `artworks()` (hasMany, ordered `position`, `id`), `exhibitions()` (hasMany),
  `portrait()` (belongsTo `App\Models\Media`, `portrait_media_id`). Helpers: `text(string $field, ?string $locale = null): ?string`
  (`{$field}_{$locale}` when non-blank, else `{$field}_fr`; blank ⇒ null), `url(): string` (`route('artists.show', $this->slug)`),
  `initials(): string` (first letters of the first two words, upper-case, multibyte-safe; one word ⇒ its first two letters).
- `Artwork` — const `AVAILABILITIES = ['none', 'available', 'reserved', 'sold', 'collection', 'commission']`;
  casts `is_published` bool, `position` int; `artist()`, `media()`; `text()` as above.
- `Exhibition` — const `KINDS = ['solo', 'group', 'residency', 'fair', 'other']`; casts `is_published` bool, `year` int,
  `starts_on`/`ends_on` `date:Y-m-d`; `artist()`, `media()`; `text()`; `status(?CarbonInterface $today = null): string`
  (§4.3).
- All three use the traits `App\Artists\Concerns\FlushesArtists` (`saved`/`deleted` ⇒ `app(ArtistDirectory::class)->flush()`),
  `HasLocalizedText` (`text()`) and `NormalisesAttributes` (before each save: `BOOLEAN_COLUMNS` as PHP bools, every
  `STRING_COLUMNS` value `mb_scrub`bed, without `"\0"`, cut to its column size — §12.5). No factories (tests build rows
  directly).

---

## 4. Core (`App\Artists`, owner: backend)

### 4.1 `App\Artists\ArtistDirectory` — the public read model (`#[Singleton]` container attribute)
Loads one **snapshot** of the published artists with their published artworks and exhibitions (3 queries, raw
JSON-safe rows: no models, no `MediaItem`, no Carbon), cached in `Cache::store(config('cms.cache.store'))` under
`artists.snapshot.v1` for `config('cms.cache.ttl')` seconds. On any `Throwable` while loading (DB down, tables
missing): log a warning, cache `['failed' => true]` for `config('cms.cache.retry')` seconds and behave as empty
(`available()` = false). When a user is logged in (`Auth::check()`), reads are **fresh** (no cache read/write) and
`find(..., withHidden: true)` may return unpublished artists. Localisation and photos are resolved at read time.

```php
public function available(): bool;   // the last load came from the database
public function flush(): void;       // forget cached + memoized snapshot (models call it on write)
public function all(?string $locale = null): array;    // list<summary> of published artists, ordered position ASC, name ASC
public function find(string $slug, ?string $locale = null, bool $withHidden = false): ?array; // full artist or null
public function neighbors(string $slug): array;        // ['prev' => ?summary, 'next' => ?summary] in all() order, wrapping; both null when < 2
public function count(): int;                          // published artists
public function sitemapEntries(): array;               // absolute url ⇒ last-modified unix timestamp|null: route('artists.index') (only when ≥ 1 artist) + every published artist
public static function media(?int $id): ?\App\Cms\MediaItem; // $id && function_exists('cms') ? cms()->media($id) : null (never throws)
public function stale(): bool;                         // the data shown is the last good copy (§12.2)
public static function maybeAdmin(?Request $request = null): bool;  // session says logged in — no DB query (§12.4)
public static function canPreview(Request $request): bool;          // maybeAdmin() + the user passes the 'admin' gate
// constants: CACHE_KEY 'artists.snapshot.v1', LASTGOOD_KEY CACHE_KEY.'.lastgood', VERSION (int, inside every cached value)
```

**Summary array** (`all()` items, `neighbors()`, start of `find()`):
`id` int · `slug` · `url` (`route('artists.show', slug)`) · `name` · `initials` · `discipline` ?string · `location` ?string ·
`statement` ?string · `accent` (one of `config('atelier.accents')`, invalid ⇒ `'yellow'`) · `portrait` ?MediaItem ·
`cover` ?MediaItem (media of the first published artwork that has a photo) · `counts` `['artworks' => int, 'exhibitions' => int]`
(published ones) · `published` bool · `example` bool · `updated_at` ?int (unix, max of the artist/its items).

**Full artist** (`find()`) = summary +
`bio_html` ?HtmlString (`App\Cms\Markdown::render()` when the class exists, else `nl2br(e())`; null when blank) ·
`meta_description` string (`meta` → `statement` → discipline + name, `Str::limit(…, 160)`) · `website` ?string ·
`instagram` ?string · `links` list `['label', 'url', 'icon' ('globe'|'instagram')]` · `artworks` list<artwork> ·
`availability_filters` list of availability keys present (excluding `none`, in `AVAILABILITIES` order) ·
`exhibitions` `['current' => list, 'upcoming' => list, 'past' => list]` · `cv` array `year ⇒ list<exhibition>` (all
published exhibitions, years DESC).

**Artwork array**: `id` · `title` · `year` ?string · `medium` ?string · `dimensions` ?string · `description` ?string ·
`availability` key · `availability_label` ?string (`__('artists.availability.{key}')`, null for `none`) · `media` ?MediaItem ·
`alt` string (media alt for the locale → `"{title} — {artist name}"`) · `caption` string (lightbox: `"{title}, {year} — {medium}, {dimensions}"`,
skipping empty parts) · `landscape` bool (media `isLandscape()`, false without media).

**Exhibition array**: `id` · `title` · `kind` · `kind_label` (`__('artists.kinds.{kind}')`) · `venue` ?string · `city` ?string ·
`year` int · `starts_on` ?string (Y-m-d) · `ends_on` ?string · `dates` string (§4.3) · `status` (`upcoming|current|past`) ·
`status_label` (`__('artists.status.{status}')`) · `description` ?string · `url` ?string · `media` ?MediaItem.

Ordering: artworks `position`, `id`; `current` by `ends_on` ASC (nulls last); `upcoming` by `starts_on` ASC; `past` and each
`cv` year by `starts_on` DESC (nulls last), then `id` DESC. EN text falls back to FR everywhere.

### 4.2 `App\Artists\MediaUsage` (static, never throws — returns `[]`/`false` on any error or missing table)
- `for(int $mediaId): array` — list of `['label' => string, 'url' => string]` (admin edit URLs):
  `__('admin_artists.usage.portrait', ['name'])` ⇒ `admin.artists.edit`; `__('admin_artists.usage.artwork', ['title','name'])`
  ⇒ `admin.artists.artworks.edit`; `__('admin_artists.usage.exhibition', ['title','name'])` ⇒ `admin.artists.exhibitions.edit`.
- `usedIds(): array` — distinct `list<int>` of every media id referenced by `artists.portrait_media_id`, `artworks.media_id`,
  `exhibitions.media_id`.
- `deletable(int $mediaId, array $ignore = []): bool` — true when the photo is used nowhere else: not `in_gallery`, no
  `service_slug`, not in `media_slots`, not a `custom_pages.cover_media_id` (each CMS check guarded by `Schema::hasTable`), and
  not referenced by any artist row except those listed in `$ignore` (`['artists' => int[], 'artworks' => int[], 'exhibitions' => int[]]`).
  Used by the "also delete the photos" options (§6).

### 4.3 `App\Artists\Dates`
- `range(?string $start, ?string $end, int $year, ?string $locale = null): string` (Carbon `translatedFormat`, locale-aware):
  none ⇒ `"2019"`; start only or same day ⇒ `"12 mars 2025"` / `"12 March 2025"`; same month ⇒ `"12 – 30 mars 2025"` /
  `"12–30 March 2025"`; same year ⇒ `"12 mars – 30 avril 2025"` / `"12 March – 30 April 2025"`; else
  `"12 décembre 2024 – 30 janvier 2025"`. French uses `1er` for the first day of a month.
- `status(?string $start, ?string $end, int $year, ?CarbonInterface $today = null): string` — dates: start > today ⇒
  `upcoming`; end ≥ today ≥ start (or start = today without end) ⇒ `current`; else `past`. Year only: year > current year ⇒
  `upcoming`, else `past`. `Exhibition::status()` delegates here.

### 4.4 `App\Artists\ExampleArtist` — the reference artist
- `exists(): bool` (an `is_example` artist exists; false on error); `create(?int $userId = null): Artist` — returns the
  existing example if there is one; otherwise imports `resources/content/artists/example.php` (§8): stores each image
  through `MediaManager::store(new UploadedFile($path, $name, 'image/webp', null, true), $variants, ['alt_fr', 'alt_en',
  'original_name'], $userId)` (variants = the bundled `{file}-{w}.webp` whose `w` ∈ `config('cms.media.widths')`; an image
  that fails is skipped, never fatal), then creates the artist (`is_example = true`, **`is_published = false`**, position
  last, slug suffixed `-exemple` when taken), artworks and exhibitions in one transaction (photos already stored are
  deleted again if it fails). Exhibition dates are relative to today, so the example always shows a current, an
  upcoming and past exhibitions. Records `Activity` `artists.example`.
- Without the CMS media classes it still creates the texts (no photos).

### 4.5 `App\Artists\MediaOptions::list(?int $selected = null): array`
`id ⇒ label` (`"#{id} · {original_name or alt_fr} ({w}×{h})"`) of the 500 most recent `Media` rows (+ `$selected` if older),
for the no-JS `<select>` fallback of photo fields; `[]` when the class/table is missing.

### 4.6 `App\Artists\Http\EnsureArtistTables` (middleware class)
Used by the three admin controllers (`HasMiddleware`): when `Schema::hasTable('artists')` is false ⇒ redirect to
`admin.maintenance` with `->with('error', __('admin_artists.errors.migrate'))`.

---

## 5. Public pages (public + backend)

`routes/artists.php` (required at top level of `routes/web.php`, before the `/{slug}` catch-all):

| Method & URI | Name | Controller@action | View |
|---|---|---|---|
| GET `/artistes` | `artists.index` | `ArtistController@index` | `artists.index` |
| GET `/artistes/{slug}` (`[a-z0-9]+(?:-[a-z0-9]+)*`) | `artists.show` | `ArtistController@show` | `artists.show` |

**View data**
| View | Variables |
|---|---|
| `artists.index` | `$artists` (list<summary>), `$available` (bool) |
| `artists.show` | `$artist` (full array), `$preview` (bool — unpublished, admin only), `$prev`, `$next` (?summary), `$others` (list<summary>, ≤ 3 other published artists) |

`show`: `find($slug, withHidden: $request->user() !== null)`; null ⇒ `abort(404)`; unpublished ⇒ only for a logged-in admin,
with `@includeIf('partials.preview-banner', ['preview' => $preview, 'label' => __('artists.preview')])` at the top of the article.

### 5.1 Design — the reference artist page (`artists.show`, body class `page-artist`, accent class = `accent-{accent}`)
Sheets: `@push('styles')` `css/pages/artists.css` (no page script: effects.js covers reveal, lightbox, filters). Only documented
primitives + own classes. Sections, top to bottom:

1. **Hero** `.artist-hero` — two columns ≥ 64em (text 7 / portrait 5), stacked below (name first). Text: `x-breadcrumbs`
   (Artistes › Name), `.eyebrow` discipline, `<h1 class="artist-hero__name">` (display font, uppercase,
   `clamp(2.5rem, 1.6rem + 4.5vw, 6rem)`, tight leading, `overflow-wrap: anywhere`, `data-split="words"`), location line
   (map-pin icon, muted, small caps), statement as `<blockquote class="artist-hero__statement">` (tagline font, step-2,
   accent seam on the inline-start side), actions (`x-button` "Voir les œuvres" → `#oeuvres`, ghost "Expositions" →
   `#expositions`, link-style "Contacter l'atelier" → `route('contact')`). Visual: **portrait** `.artist-portrait`
   (4/5, `object-fit: cover` with the focal point, eager + `fetchpriority="high"`), framed by a white seam inset, an
   accent triangle in the bottom-right corner and a small Mondrian block cluster offset behind; reveal keyframe on load.
   No portrait ⇒ the key work (`cover`) in the same frame; neither ⇒ **monogram** `.artist-monogram` (initials in outlined
   display type over a slowly shifting Mondrian field in the accent colour, `.ap-anim-scope`).
2. **Facts band** `.artist-facts` — a Mondrian-like row of cells separated by white seams: Œuvres `08`, Expositions `06`
   (zero-padded, display font, `.gradient-text`), Discipline, Basé·e à, Liens (site web, Instagram — new tab,
   `rel="noopener"`, visually-hidden "nouvel onglet"). Cells with no value are omitted.
3. **Biography** `.artist-bio` (only when `bio_html`) — `.section`, heading column (x-section-heading "Biographie") + `.prose`
   (max 68ch; first paragraph larger; an accent drop cap in display font on the first letter); sticky aside
   (`.sticky-aside`) with the key work (natural ratio, caption) and a "Fiche" `<dl>` (discipline, location, counts, links,
   CTA "Demander des informations" → contact).
4. **Works** `#oeuvres` `.artist-works` `.section--surface` (only when artworks) — x-section-heading "Œuvres choisies" + count;
   availability filter chips (`data-filter-group`, `x-chip`) when ≥ 4 works and `availability_filters` not empty: Toutes +
   each present availability; grid `.artist-works__grid` (`repeat(auto-fill, minmax(min(100%, 17rem), 1fr))`,
   `grid-auto-flow: dense`; `.artwork--landscape` spans 2 columns ≥ 64em only). Card `.artwork` (`data-filter-item`
   `data-category="{availability}"`, `data-reveal`): a "wall mat" (`--ap-coal`, padding, deep shadow) holding the image at its
   **natural ratio** (`aspect-ratio` from `MediaItem::ratio()`), link `a[data-lightbox="artworks"][data-caption]` to the 1600
   url; caption: *title* (italic), year · technique · dimensions (muted), availability pill with a coloured dot
   (available yellow, reserved amber, sold red-bright, collection blue-bright, commission orange); description in a
   `<details>` when present. Works without a photo: a text card on a Mondrian placeholder.
5. **Exhibitions** `#expositions` `.artist-exhibitions` (only when any) — "Actualité" (current then upcoming) as wide cards
   `.exhibition-feature` (visual 16/10 when present, status pill — current pulses —, title, venue · city, dates, description,
   "En savoir plus" external link); then **CV** `.artist-cv`: two columns ≥ 40em, year in large outlined numerals, entries =
   kind (small caps, accent), *title* (italic), venue, city, dates; a thin vertical line draws in. Past exhibitions with a
   visual show a small thumbnail.
6. **More artists** `.artist-more` (when `$others`): prev/next links (`rel`) + up to 3 cards (`artists.partials.card`) +
   "Tous les artistes".
7. `x-cta-band` — "Une œuvre vous intéresse ?" → `route('contact')`, secondary "Tous les artistes".

Head: `@section('title', name)`, `meta_description`, `@section('og_image', portrait/cover `url(1600)` or '')`, JSON-LD
(`@push('head')`, same encoding flags as `services.show`): `Person` (`name`, `url`, `image`, `jobTitle` = discipline,
`description`, `sameAs` = links, `homeLocation` = location) + `ExhibitionEvent` items for current/upcoming exhibitions
(`name`, `startDate`, `endDate`, `location` {Place name/address}).

### 5.2 `artists.index` (body class `page-artists`)
`x-page-hero` (eyebrow, title "Artistes", lead, breadcrumbs, accent `orange`, actions "Découvrir les artistes" → `#artistes`
and ghost "Exposer avec nous" → contact; aside = overlapping triangle-cut tiles of up to 5 artists' portraits/covers, or
`x-mondrian` when none). Section `#artistes`: count line + grid `.artists-grid` of `artists.partials.card` (portrait/cover/
monogram 4/5 in a seam frame with an accent triangle corner, name in display font, discipline, location, "8 œuvres ·
6 expositions", the whole card is one link with an arrow). Empty or unavailable ⇒ `.artists-empty` (Mondrian art, title,
text, contact button) — HTTP 200. Closing `x-cta-band` "Vous êtes artiste ?" → contact, secondary → `route('about')`.

### 5.3 Partials (`resources/views/artists/partials/`)
`card` (`$artist` summary, `$index`), `image` (`$media` MediaItem, `$alt`, `$sizes`, `$width` = 960, `$lazy` = true,
`$class` = null, `$cover` = false: `<img>` with `src` = `url($width)`, `srcset`, `sizes`, `width`/`height`, `alt`,
`loading`/`decoding`, `style="object-position: …"` when `$cover`), `monogram` (`$artist`), `artwork` (`$artwork`, `$artist`),
`exhibition` (`$exhibition`, `$variant` = `feature|cv`).

### 5.4 CSS `public/css/pages/artists.css`
Tokens only; classes `.artists-*`, `.artist-*`, `.artwork*`, `.exhibition*`. Mobile-first (40/64/80em), no horizontal
overflow at 320px, complete composition with motion off, focus-visible styles kept. ≥ 6 keyframes
`ap-pg-artists-*` (`@anim group=page`, demos from the catalogue), each **used**: portrait reveal (shape), frame seam
(bar), monogram drift (blocks), "current" pulse (ring), CV line (bar), artwork sheen (panel). After adding keyframes run
`python3 python/tools/animations.py` (regenerates `resources/content/animations.json`) then `--check`.

---

## 6. Admin (admin + backend)

`routes/admin-artists.php` is required **inside** the CMS group that already adds the `/admin` prefix, the `admin.` name
prefix and `web`/`admin.headers`/`auth`/`cms.ready`: write relative URIs and short names. `{artist}`, `{artwork}`,
`{exhibition}` = implicit model binding by id (`whereNumber`), child bindings scoped to the artist (`scopeBindings()`).

| Method & URI (under /admin) | Name (`admin.` +) | Controller@action |
|---|---|---|
| GET `/artistes` | `artists.index` | `Admin\ArtistController@index` |
| POST `/artistes/ordre` | `artists.reorder` | `@reorder` |
| POST `/artistes/exemple` | `artists.example` | `@example` |
| GET `/artistes/creer` | `artists.create` | `@create` |
| POST `/artistes` | `artists.store` | `@store` |
| GET `/artistes/{artist}` | `artists.edit` | `@edit` |
| PUT `/artistes/{artist}` | `artists.update` | `@update` |
| POST `/artistes/{artist}/visibilite` | `artists.toggle` | `@toggle` |
| POST `/artistes/{artist}/portrait` | `artists.portrait` | `@portrait` |
| DELETE `/artistes/{artist}` | `artists.destroy` | `@destroy` |
| GET `/artistes/{artist}/oeuvres` | `artists.artworks.index` | `Admin\ArtworkController@index` |
| POST `/artistes/{artist}/oeuvres` | `artists.artworks.store` | `@store` |
| POST `/artistes/{artist}/oeuvres/ordre` | `artists.artworks.reorder` | `@reorder` |
| GET `/artistes/{artist}/oeuvres/{artwork}` | `artists.artworks.edit` | `@edit` |
| PUT `/artistes/{artist}/oeuvres/{artwork}` | `artists.artworks.update` | `@update` |
| POST `/artistes/{artist}/oeuvres/{artwork}/image` | `artists.artworks.image` | `@image` |
| DELETE `/artistes/{artist}/oeuvres/{artwork}` | `artists.artworks.destroy` | `@destroy` |
| GET `/artistes/{artist}/expositions` | `artists.exhibitions.index` | `Admin\ExhibitionController@index` |
| GET `/artistes/{artist}/expositions/creer` | `artists.exhibitions.create` | `@create` |
| POST `/artistes/{artist}/expositions` | `artists.exhibitions.store` | `@store` |
| GET `/artistes/{artist}/expositions/{exhibition}` | `artists.exhibitions.edit` | `@edit` |
| PUT `/artistes/{artist}/expositions/{exhibition}` | `artists.exhibitions.update` | `@update` |
| POST `/artistes/{artist}/expositions/{exhibition}/image` | `artists.exhibitions.image` | `@image` |
| DELETE `/artistes/{artist}/expositions/{exhibition}` | `artists.exhibitions.destroy` | `@destroy` |

Conventions (CMS §7.0): flash `->with('status', …)` / `->with('error', …)`; invalid forms ⇒
`App\Http\Controllers\Admin\Concerns\RendersInvalidForms::invalid($request, $view, $viewData, $validator)` (422); every write
records `App\Cms\Activity::record('artists.{action}', __('admin_artists.activity.{action}', …), 'artist:{id}')` (guarded by
`class_exists`); validation attribute names from `__('admin_artists.attributes')`; reorder endpoints answer JSON
`{message}` when `expectsJson()`, else redirect back with `status`.

### 6.1 Controllers & rules
- **ArtistController** — `index`: view `admin.artists.index` (`$artists` = Collection of `Artist` with `artworks_count`,
  `exhibitions_count`, ordered `position`, `name`; `$hasExample` bool). `create`: view `admin.artists.create` (`$artist` new
  `Artist` with `accent='yellow'`, `$accents`). `store`: `name` required ≤120; `slug` nullable ⇒ `Str::slug(name)`, ≤80,
  regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`, unique; `discipline_fr`, `discipline_en` ≤120; `location` ≤120; `accent` ∈
  `config('atelier.accents')` ⇒ created unpublished, position last, `updated_by` ⇒ redirect `admin.artists.edit` +
  `flash.created`. `edit`: view `admin.artists.edit` (`$artist`, `$portrait` ?MediaItem, `$mediaOptions`
  (`MediaOptions::list`), `$accents`). `update`: the store rules + `slug` required & unique except self; `statement_fr`,
  `statement_en` ≤400; `bio_fr`, `bio_en` ≤10000; `meta_fr`, `meta_en` ≤170; `website`, `instagram` nullable `url:https`
  ≤255; `portrait_media_id` nullable integer `exists:media,id`; `is_published` boolean. Line endings normalised to `\n`,
  blank strings ⇒ null. `toggle`: flip `is_published` ⇒ back + `flash.published|hidden`. `reorder`: `order` = array of
  existing artist ids ⇒ positions 1…n. `destroy`: optional `delete_photos` (bool) ⇒ before deleting, collect the photo ids
  of the artist + its artworks/exhibitions and delete those `MediaUsage::deletable(id, ignore: this artist's rows)` through
  `MediaManager::delete()`; then delete (cascade) ⇒ index + `flash.deleted`. `example`: `ExampleArtist::create(auth()->id())`
  ⇒ edit page + `flash.example_created` (or `flash.example_exists`).
- **ArtworkController** — `index`: view `admin.artists.artworks.index` (`$artist`, `$artworks` with `media` resolved:
  each item `['model' => Artwork, 'media' => ?MediaItem]` or the models + `ArtistDirectory::media()` in the view).
  `store` (bulk uploads, JSON or form): `photo` file (+ `variants[480|960|1600]`, `original_name`) **or** `media_id`
  (exists) — neither ⇒ 422 `errors.photo_required`; photo ⇒ `MediaManager::store($photo, $variants, ['alt_fr' => $title,
  'original_name' => …], auth()->id())`, `InvalidImage` ⇒ 422 with `__('admin_media.errors.'.$e->reason)`; `title_fr`
  optional ⇒ default = humanised original name without extension (`"mon_tableau-02.jpg"` ⇒ `"Mon tableau 02"`) or
  `__('admin_artists.artworks.untitled')`; creates the artwork (published, availability `none`, position last). JSON
  (`Accept: application/json`, the CMS uploader) ⇒ **201** `{media: MediaItem::toArray() + ['edit_url' =>
  route('admin.media.edit', id)], artwork: {id, title, edit_url}, message}`; 422 `{message, errors}`; form ⇒ redirect to
  the artwork edit page + `flash.artwork_created`. `edit`: view `admin.artists.artworks.edit` (`$artist`, `$artwork`,
  `$media` ?MediaItem, `$mediaOptions`, `$availabilities`). `update`: `title_fr` required ≤160, `title_en` ≤160, `year`
  ≤20, `medium_fr`/`medium_en` ≤160, `dimensions` ≤80, `description_fr`/`description_en` ≤1500, `availability` ∈
  `AVAILABILITIES`, `is_published` boolean, `media_id` nullable exists. `image` (replace the photo file — CMS uploader,
  one file): artwork has media ⇒ `MediaManager::replace($media, $photo, $variants)` (same photo row, new file everywhere it
  is used); none ⇒ `store` + link; JSON 201 `{media, message}` / redirect. `reorder`: `order` = ids of this artist's
  artworks. `destroy`: optional `delete_photo` ⇒ delete the photo when `MediaUsage::deletable(id, ['artworks' => [id]])`.
- **ExhibitionController** — `index`: view `admin.artists.exhibitions.index` (`$artist`, `$groups` =
  `['current' => Collection, 'upcoming' => …, 'past' => …]` of `Exhibition`). `create`/`edit`: view
  `admin.artists.exhibitions.form` (`$artist`, `$exhibition` (new: `kind='group'`, `year` = current year,
  `is_published=true`), `$media` ?MediaItem, `$mediaOptions`, `$kinds`). `store`/`update`: `title_fr` required ≤160,
  `title_en` ≤160, `kind` ∈ `KINDS`, `venue` ≤160, `city` ≤120, `year` required integer 1900…(current+10), `starts_on`
  nullable date, `ends_on` nullable date `after_or_equal:starts_on` (requires `starts_on`), `url` nullable `url:https` ≤255,
  `description_fr`/`description_en` ≤1500, `media_id` nullable exists, `is_published` boolean; a `starts_on` sets `year`
  to its year ⇒ exhibitions index + flash. `destroy` ⇒ exhibitions index + `flash.exhibition_deleted`.

### 6.1b Adding images from the artist screens (as built)
- `artists.portrait` — the portrait card sits **outside** the profile form and applies at once: `photo` (+ `variants[w]`,
  `original_name`; the CMS uploader → 201 `{media, message}`, or a plain multipart form → redirect + `status`) **or**
  `media_id` (a library photo; empty = no portrait). `artists.exhibitions.image` — same upload for an exhibition's visual
  (edit screen only; on creation the form says to save first).
- `App\Artists\Photos::put()`: a new file **replaces the current photo's file in place** when `MediaUsage::deletable()`
  says nothing else uses it (no orphan in the library), otherwise it is **stored as a new photo** and the old one stays
  where it is used (gallery, spots, other rows). The artwork image endpoint replaces the artwork's photo in place.
- Refusals: JSON 422 `{message, errors: {photo|media_id}}`; plain forms redirect back with `error`.
- Other inputs added while building: `move` (`up|down`, no-JS reorder buttons) and a safe `redirect`
  (`SafeUrl::internal()`) on the reorder/toggle endpoints; `source=library` on the artwork "photo of the library" form.
- Links to the CMS library screens (`admin.media.edit`) appear only once `view('admin.media.edit')` exists.

### 6.2 Admin views (`resources/views/admin/artists/`, `@extends('admin.layouts.app')`, `x-admin.*` components, `.adm-*` classes)
- `index` — `x-admin.page-head` "Artistes" (lead: one page per artist — biography, works, exhibitions) with actions "Ajouter
  un artiste" (+ "Créer la page exemple" POST form when `! $hasExample`); `x-admin.empty` (icon `palette`) when none;
  otherwise a sortable list (`form` → `artists.reorder`, `[data-sortable][data-sortable-autosubmit]`, hidden `order[]`,
  handle + `[data-move]` buttons): thumb (portrait or first artwork), name + discipline, counts, badges (Publié
  `--success` / Brouillon `--hidden` / Exemple `--custom`), actions Modifier, Œuvres, Expositions, Voir/Aperçu (new tab),
  Publier/Masquer (`artists.toggle`).
- `partials/subnav` (`$artist`, `$current` ∈ `profile|artworks|exhibitions`) — tabs-as-links "Profil", "Œuvres (n)",
  "Expositions (n)" + "Voir la page"/"Aperçu" (new tab) + status badge; `aria-current="page"` on the current one.
- `partials/media-field` (`$name`, `$id`, `$label`, `$media` ?MediaItem, `$options`, `$ratio`, `$hint` = null) — CMS picker
  **field mode**: wrapper `[data-media-field]`, preview `[data-media-preview]` (img or empty triangle placeholder at
  `$ratio`), button `[data-media-picker][data-media-target="#{id}"]` "Choisir ou ajouter une photo", button
  `[data-media-clear]` "Retirer", both `hidden` until JS converts the field; **no-JS fallback** = `<select name="{name}"
  data-media-select data-media-input-id="{id}">` (empty option "— Aucune photo —" + `$options`). `artists.js` converts it
  (§6.3).
- `create` — name, slug (auto-filled from the name until edited), discipline FR | EN, location, accent swatches ⇒ store.
- `edit` — page-head (name, public URL, back to index) + subnav + example notice (`.alert`) when `is_example`; one form
  (PUT, `data-adm-dirty`): card "Identité" (name, slug, discipline pair, location, accent swatches, website, instagram),
  card "Portrait" (media-field `portrait_media_id`, ratio 4/5, hint: vertical photo; without one a monogram is shown),
  card "Présentation" (statement pair ≤400 with counters, bio pair = Markdown textareas (rows 14, autosize, ≤10000) with a
  `<details>` syntax help, meta pair ≤170), card "Publication" (`x-admin.toggle is_published`), `x-admin.savebar`; danger
  card: `x-admin.confirm` delete + checkbox `delete_photos`.
- `artworks/index` — page-head + subnav; uploader card: `@include('admin.media.partials.uploader', ['action' =>
  route('admin.artists.artworks.store', $artist), 'multiple' => true, 'defaults' => [], 'redirect' =>
  url()->current(), 'label' => __('admin_artists.artworks.upload')])` (hint: one photo per artwork, several at once, from a
  phone too); sortable list (thumb, *title*, year · medium · dimensions, availability + Masquée badges, Modifier,
  Supprimer) or `x-admin.empty`.
- `artworks/edit` — page-head (title, back to works) + subnav; two columns ≥ 64em: left = image card (large preview at
  natural ratio; "Remplacer le fichier" = uploader include with `action` = `artists.artworks.image`, `multiple` false;
  link "Texte alternatif, point focal…" ⇒ `admin.media.edit`); right = form (PUT, `data-adm-dirty`): title pair, year,
  medium pair, dimensions, availability (radio chips), description pair, media-field `media_id` ("Utiliser une autre photo
  de la bibliothèque"), published toggle, savebar; danger card: delete + `delete_photo`.
- `exhibitions/index` — page-head + subnav + "Ajouter une exposition"; three groups (En cours, À venir, Passées), rows: dates
  (or year), *title*, kind, venue · city, Masquée badge, Modifier / Supprimer; `x-admin.empty` when none.
- `exhibitions/form` — create & edit: title pair, kind (select), venue, city, year, starts_on / ends_on (`type="date"`,
  hint: optional, the year follows the start date), url, description pair, media-field `media_id` (ratio 16/10),
  published toggle, savebar; delete card on edit.

### 6.3 `public/js/admin/artists.js` (deferred IIFE, `'use strict'`, `window.AP`; pushed by the admin artist views)
1. Slug: `[data-artist-slug-source]` (name) fills `[data-artist-slug]` with a slugified value while the slug field was not
   edited by hand (create page).
2. Photo fields: only when the CMS picker script is on the page (`script[src*="js/admin/media-picker.js"]`): for each
   `[data-media-field] select[data-media-select]`, create `<input type="hidden" id="{data-media-input-id}"
   name="{select.name}" value="{select.value}">`, remove the select's `name`, hide it, un-hide `[data-media-picker]` and
   `[data-media-clear]`, and keep the preview in sync on the hidden input's `change` (the CMS picker sets the value).
3. Exhibition form: a `starts_on` change copies its year into `year`.
The views push `js/admin/artists.js` and — only when the files exist — `js/admin/uploader.js` and `js/admin/media-picker.js`
(`@push('scripts')`, deferred).

### 6.4 CSS `public/css/admin/artists.css`
Prefix `.adm-artist-*` (subnav, media field preview/placeholder at a ratio, artwork rows, accent swatches, example notice,
exhibition groups). Tokens only; any keyframes named `ap-adm-artists-*` with an `@anim` comment. Mobile-friendly
(phones upload photos), no horizontal overflow at 320px.

---

## 7. Internationalisation

`lang/{fr,en}/artists.php` (public; editable in the CMS "Textes des pages" once it exists) and
`lang/{fr,en}/admin_artists.php` (admin) — identical key structures, French first, natural English. Keys used across areas
(must exist):
- `artists.nav` (header/footer link label, used by the public-site session), `artists.preview` (preview banner label),
  `artists.availability.{available,reserved,sold,collection,commission}`, `artists.kinds.{solo,group,residency,fair,other}`,
  `artists.status.{upcoming,current,past}`.
- `admin_artists.nav` (CMS sidebar), `admin_artists.flash.{created,updated,deleted,published,hidden,reordered,
  example_created,example_exists,artwork_created,artwork_updated,artwork_deleted,artworks_reordered,image_replaced,
  exhibition_created,exhibition_updated,exhibition_deleted}`, `admin_artists.errors.{migrate,photo_required,order}`,
  `admin_artists.activity.{created,updated,deleted,published,hidden,reordered,example,artwork_created,artwork_updated,
  artwork_deleted,image_replaced,exhibition_created,exhibition_updated,exhibition_deleted}`,
  `admin_artists.attributes.{…every validated field…}`, `admin_artists.artworks.untitled`,
  `admin_artists.artworks.upload`, `admin_artists.usage.{portrait,artwork,exhibition}`.
Placeholders: `:name` (artist), `:title` (artwork/exhibition), `:count`. Pluralised lines use `|` with `trans_choice`.

## 8. The example artist — `resources/content/artists/example.php` (+ `resources/content/artists/example/`)

A fictional artist (**Camille Durand**, painter & generative artist, Lyon), unpublished and flagged, whose content shows
every feature: statement, a 3–4 paragraph Markdown bio (headings not needed; bold/italic/lists allowed), 8 artworks with
varied ratios (portrait, square, landscape, panoramic) and every availability, 6 exhibitions (1 current, 1 upcoming,
4 past incl. year-only entries; kinds solo/group/residency/fair). No real institutions, galleries or people: venues are
fictional or the atelier itself. FR + EN.

```php
return [
    'artist' => [
        'slug' => 'camille-durand', 'name' => 'Camille Durand', 'accent' => 'yellow', 'location' => 'Lyon, France',
        'website' => null, 'instagram' => null,
        'portrait' => ['file' => 'portrait', 'alt' => ['fr' => '…', 'en' => '…']],
        'fr' => ['discipline' => '…', 'statement' => '…', 'bio' => "…markdown…", 'meta' => '…'],
        'en' => [ /* same keys */ ],
    ],
    'artworks' => [ // in display order
        ['file' => 'oeuvre-01', 'year' => '2025', 'dimensions' => '100 × 80 cm', 'availability' => 'available',
         'fr' => ['title' => '…', 'medium' => '…', 'description' => '…'], 'en' => [ /* same keys */ ]],
    ],
    'exhibitions' => [
        // dates: modifiers for today->modify() (e.g. '-20 days'), or null; 'years_ago' for year-only entries.
        ['kind' => 'solo', 'venue' => '…', 'city' => '…', 'starts' => '-20 days', 'ends' => '+40 days', 'years_ago' => null,
         'url' => null, 'file' => 'expo-01', 'fr' => ['title' => '…', 'description' => '…'], 'en' => [ /* same keys */ ]],
    ],
];
```
Every image entry (`portrait`, each artwork, each exhibition with a `file`) may carry `'alt' => ['fr' => …, 'en' => …]`,
stored as the photo's alternative text (else "{title} — {artist}" is used on the page).
Images (WebP, sRGB, no metadata): `{file}.webp` (longest edge 1600) + variants `{file}-480.webp`, `{file}-960.webp`
(same aspect ratio ±1 px). Artworks are compositions rendered by the atelier's own art engine (`python/art_engine`, one
style per work) — honest about being generative prints (technique e.g. "Impression pigmentaire sur papier coton").
The portrait is a stylised geometric bust (logo-palette facets, white seams — clearly an artwork, not a photo); the
exhibition visuals are rendered "installation views" (dark gallery wall, framed works). Total ≤ 3 MB.

## 9. Hooks provided by the other sessions
- CMS (f0): route requires (done), reserved slug `artistes` (done), sidebar item (`Route::has('admin.artists.index')`,
  `__('admin_artists.nav')`, icon `palette`, accent orange), uploader `$action` + picker field mode (§6.2), media usage
  merge (`MediaUsage::for/usedIds`), sitemap merge (`ArtistDirectory::sitemapEntries()`), editable group `artists`,
  dashboard stat + JSON export of the three tables.
- Public site (bf): header + footer link to `route('artists.index')` (guarded by `Route::has`), label `__('artists.nav')`.

## 10. Tests (`tests/Feature/Artists/**`, `RefreshDatabase` + `Tests\Concerns\InteractsWithCms`)
Public: index lists published only, ordered; empty state 200; show renders hero/bio/works/exhibitions/CV/JSON-LD in FR and
EN (EN falls back to FR); unpublished ⇒ 404 for guests, 200 + preview banner for an admin; unknown slug ⇒ 404; tables
missing / DB failure ⇒ index 200 + show 404 (negative cache); photos render `/media/…` URLs with srcset. Admin: guests ⇒
login; CRUD + validation 422 (no `_old_input` in the persisted session) for artists, artworks, exhibitions; slug rules;
reorder; toggle; JSON 201 upload of an artwork (PNG from `pngFile()`), invalid file ⇒ 422; image replace keeps the media
id and changes the ulid; delete with/without photos respects `MediaUsage::deletable`; example creation is idempotent and
imports the bundled photos. Core: `Dates::range/status` (both locales, edge cases), `ArtistDirectory` cache flush on
writes, `MediaUsage::for/usedIds/deletable`, fr/en key parity of the two new groups.

## 12. Amendments (BINDING — mirror docs/CMS.md §13; they override earlier sections where they differ)

1. **Snapshot contents** (CMS §13 A2): `artists.snapshot.v1` holds only arrays/strings/ints/bools/null (dates as
   `Y-m-d`/ISO strings, media as ids) plus a `version` key (`ArtistDirectory::VERSION`, int); anything else read back
   (non-array, `__PHP_Incomplete_Class`, wrong/missing version, the failed marker of another version) is a **miss**.
   Tests run it through a serializing store (`file` store in a temp dir or `Cache::store('file')`), not only `array`.
2. **Stale-if-error** (A3): every successful public load also writes `artists.snapshot.v1.lastgood` (no TTL); a failed
   refresh serves the last-good copy (and `available()` stays true for content purposes, `stale()` = true); only with
   no copy at all is the module "unavailable". Then: `/artistes` renders its calm unavailable state with **HTTP 503**,
   `Retry-After: 300`, `Cache-Control: no-store`; `/artistes/{slug}` answers **503** with the same headers (not 404);
   `sitemapEntries()` returns `[]`. A slug that is simply unknown while the data IS available stays a 404.
3. **Circuit breaker** (A1): when `App\Cms\DatabaseHealth` exists, every public DB touchpoint of the module
   (snapshot load, public lookups, `MediaUsage`, `ExampleArtist::exists()`) checks it first and skips the DB while it
   is open, and reports a connection failure to it. API (container singleton, guard with `class_exists`):
   `available(): bool` (false while open), `failed(Throwable $e): void`, `attempt(callable $callback, mixed $fallback =
   null): mixed` (runs the callback unless open; on any Throwable calls `failed()` and returns `$fallback`) — wrap the
   snapshot load and public lookups in `app(DatabaseHealth::class)->attempt(fn () => …, $fallback)`.
4. **Admin detection without the DB** (A5): public code decides "may be an admin" from the session only —
   `$request->hasSession() && $request->session()->has(Auth::guard('web')->getName())` in a try/catch — never
   `Auth::check()`/`$request->user()` for guests. Only when that is true: fresh (uncached) reads, and the unpublished
   preview requires `$request->user()?->can('admin')` (`Gate::has('admin')` ⇒ the gate, else any user).
5. **Postgres** (B7): booleans written and queried only as PHP bools (`$request->boolean()`, `true/false`), with a
   bool-normalising mutator for `is_published`/`is_example`; every string `mb_scrub`bed, stripped of `"\0"` and cut to
   its column size before insert; no exception swallowed inside `DB::transaction` (ExampleArtist: store photos first,
   create the rows in a transaction that rethrows, delete the stored photos **outside** it on failure). Reorders run in
   a transaction and flush the snapshot after commit (`DB::afterCommit`).
6. **Forms** (E21): admin HTML forms use `Validator::make()` + `$this->invalid()` (422) — never `$request->validate()`,
   `ValidatesRequests` or FormRequest; JSON endpoints may answer 422 JSON. Tests assert 422 (not 302); one test posts an
   invalid long form with `SESSION_DRIVER=cookie` and asserts every `Set-Cookie` header is < 4096 bytes. Every field has
   a max length.
7. **URLs** (E22): `redirect` inputs go through `App\Cms\SafeUrl::internal($url, $fallback)`; `website`/`instagram` must
   pass `SafeUrl::external()` on save and again when rendered (fallback when the class is missing: `https` scheme +
   `FILTER_VALIDATE_URL` + no backslash/whitespace).
8. **Photos** (C9/C10): `cms.media.widths` = `[480, 960]`, `max_edge` 1920 — the bundled example images (longest edge 1600 +
   `-480`/`-960`) comply. `InvalidImage` reasons now include `quota` and `interrupted`: the artwork upload answers 422
   with `__('admin_media.errors.'.$reason)`; `ExampleArtist` skips a photo that fails for any reason (incl. quota) and
   reports how many photos were imported in its flash message.
9. **Admin right** (D18): the admin group adds `can:admin` + `auth.session`; tests create admins with `is_admin = true`
   when the column exists (prefer `Tests\Concerns\InteractsWithCms::admin()` once it exists, guarded).
10. **EnsureCmsReady** (E23) already requires every `2026_10_03_*` migration — including ours — so a pending artists
   migration sends the admin to Maintenance; `EnsureArtistTables` stays as a second guard.
11. **Admin bar** (CMS §13 F29): `artists.show` sets `@section('admin_edit_url', route('admin.artists.edit', id))` and
   `@section('admin_edit_label', __('artists.show.admin_edit'))` ("Modifier cet artiste" / "Edit this artist");
   `artists.index` sets them to `route('admin.artists.index')` / `__('artists.index.admin_edit')` ("Gérer les
   artistes" / "Manage artists"). The CMS admin bar shows the link to logged-in admins only.
12. **Long fields never flashed**: the CMS adds `bio_fr, bio_en, statement_fr, statement_en, description_fr,
   description_en` to `dontFlash` (safety net only — our forms never flash input anyway).
13. Production already has users (the owner is user #1): never assume an empty `users` table.

## 11. Verification
```bash
XDEBUG_MODE=off php bin/blade-lint.php resources/views
XDEBUG_MODE=off php artisan test
python3 python/tools/animations.py --check
python3 python/tools/palette_audit.py
XDEBUG_MODE=off vendor/bin/pint --test app/Artists app/Models/Artist.php app/Models/Artwork.php app/Models/Exhibition.php app/Http/Controllers/ArtistController.php app/Http/Controllers/Admin/ArtistController.php app/Http/Controllers/Admin/ArtworkController.php app/Http/Controllers/Admin/ExhibitionController.php routes/artists.php routes/admin-artists.php database/migrations/2026_10_03_100000_create_artists_tables.php tests/Feature/Artists
```
