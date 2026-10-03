# Ateliers Pehouet — Architecture & Build Contract

> *L'art au service de la communauté.*
> This document is the **binding contract** between every part of the site. When code and
> this document disagree, fix the code (or update this document deliberately).

- **Laravel 13** (PHP 8.3+) — routing, controllers, Blade views, contact form, i18n, the bridge to Python.
- **HTML + CSS** — hand-written, no framework, no build step. Files live in `public/css` and load directly.
- **Vanilla JavaScript** — small deferred scripts in `public/js`, no bundler.
- **Python 3.11+ (stdlib only)** — `python/art_engine`: a generative-art engine (HTTP micro-service + CLI)
  that paints compositions in the logo's style; plus QA tools in `python/tools`.

Default language **French**, secondary **English** (session-based switch). All copy goes through `__()`.

---

## 1. Brand & art direction

The logo is a **triangle "A"** split into Mondrian-like colour fields (blue, yellow, white, red, black)
with white seams, followed by **TELIERS** in rounded techno capitals painted with a
yellow → amber → orange → red gradient and a red neon halo, the tagline in a thin rounded font,
and a white, spiky **signature "Pehouet"** made of long diagonal slashes — all on **pure black**.

Reference image: `public/images/logo-ateliers-pehouet.png` (open it with your image viewer/Read tool).

### Palette (only these colours — enforced by `python/tools/palette_audit.py`)

| Token | Hex | Use |
|---|---|---|
| `--ap-black` | `#000000` | page background (the logo's ground) |
| `--ap-ink` / `--ap-coal` / `--ap-graphite` / `--ap-steel` | `#08080b` `#121216` `#1d1d23` `#2c2c34` | surfaces, borders |
| `--ap-smoke` / `--ap-mist` | `#898789` `#c4c2c5` | tagline grey, secondary text |
| `--ap-white` | `#fafcfd` | text, seams, signature |
| `--ap-blue` (+`-bright` `#4f8edc`, `-deep` `#173d6b`) | `#265fa5` | left field of the triangle |
| `--ap-yellow` | `#f8d449` | top field of the triangle |
| `--ap-amber` | `#e3a94e` | the "T" of TELIERS |
| `--ap-orange` | `#c96338` | the "E" of TELIERS |
| `--ap-red` (+`-bright` `#e8433b`, `-deep` `#6e1a1a`) | `#b32c2b` | right field, the "RS", neon |

Rules:
- **Never write a raw colour outside `public/css/01-tokens.css`.** Use `var(--ap-…)` tokens, the
  translucent helpers (`--ap-white-08`, `--ap-red-glow`…), the gradients (`--ap-gradient-teliers`,
  `--ap-gradient-tri`, `--ap-gradient-prism`), or the **accent classes** below. Inline SVG in Blade
  may use palette hex values in attributes when CSS is impractical (allowed list = table above +
  `#fff`/`#000`). `rgb()`/`rgba()` literals must also be palette colours (any alpha).
- Small text in red/blue uses the `-bright` variants (contrast ≥ 4.5:1 on black).
- **Accent classes** (`.accent-blue|yellow|red|orange|amber|white`) set `--accent`,
  `--accent-bright` (text-safe), `--accent-ink` (text placed on an accent fill), `--accent-glow`.
  Services, categories and components colour themselves through these.

### Typography
- Display / headings: `--ap-font-display` (**Audiowide** — matches TELIERS). Usually uppercase, slight tracking.
- Body: `--ap-font-body` (**Exo 2**, variable 300–800).
- Taglines / eyebrows / quotes: `--ap-font-tagline` (**Comfortaa** — matches the logo tagline).
- Fluid scale: `--ap-step--1 … --ap-step-6`.

### Motifs (use them everywhere — the site must feel *made by an artist*)
1. **The triangle** — clip-paths (`--ap-clip-tri`…), outlines, tessellations, triangle particles.
2. **Mondrian fields** — blocks of blue/yellow/red/white/black separated by white seams (`--ap-seam`).
3. **The TELIERS gradient + neon** — headings in `.gradient-text`, glowing `.neon-text`.
4. **Signature slashes** — long thin white diagonal strokes (≈ −20°) and steep strokes (≈ 65°).
5. **Black canvas** — generous negative space, light emerging from darkness.

Layouts should be bold and asymmetric: diagonal section edges, oversized outlined numerals,
overlapping colour blocks, sticky asides, mixed font sizes. Avoid generic "SaaS" looks.

### The logo mark geometry (canonical — reuse it exactly)
ViewBox `-3 -3 106 92.6` (triangle 100 × 86.6). Each piece: fill = colour, `stroke: var(--ap-white)`,
`stroke-width ≈ 1`, `stroke-linejoin: round`.

```
yellow  points="50,0 78.98,50.2 39.3,50.2 39.3,18.53"
blue    points="39.3,18.53 39.3,68.8 10.28,68.8"
white   points="39.3,50.2 59.3,50.2 59.3,86.6 54.8,86.6 54.8,68.8 39.3,68.8"
red     points="59.3,50.2 78.98,50.2 100,86.6 59.3,86.6"
black   points="10.28,68.8 54.8,68.8 54.8,86.6 0,86.6"
outline points="50,0 100,86.6 0,86.6"   (fill none, white, stroke-width ≈ 1.6)
```

---

## 2. Work areas & file ownership

Each area owns its files exclusively. Read anything; write only what you own.

| Area | Owns |
|---|---|
| **A · Python engine** | `python/**` (`art_engine/`, `tools/`, `tests/`, `README.md`, `requirements.txt`) |
| **B · Backend** | `routes/**`, `app/**` (except `app/helpers.php`), `bootstrap/app.php`, `database/**`, `config/*` except `config/atelier.php`, `resources/views/mail/**`, `resources/views/sitemap.blade.php`, `lang/{fr,en}/validation.php`, `lang/{fr,en}/mail.php`, `tests/**`, `composer.json` scripts |
| **C · Design system & shell** | `public/css/02-base.css`, `03-layout.css`, `05-anim-brand.css`; `resources/views/partials/**`; components `logo-mark`, `logo-wordmark`, `signature`; `public/js/head.js`, `core.js`, `nav.js`, `fx.js`, `loader.js`; `lang/{fr,en}/ui.php` |
| **D · Components & effects** | `public/css/04-components.css`, `07-anim-ui.css`; `public/js/effects.js`; all components in §8.2; `lang/{fr,en}/components.php` |
| **E · Ambient & Motion page** | `public/css/06-anim-ambient.css`; components in §8.3; `resources/views/pages/motion.blade.php`; `public/css/pages/motion.css`; `public/js/motion.js`; `lang/{fr,en}/motion.php` |
| **F · Story pages** | `pages/home|about|community.blade.php`; `public/css/pages/home|about|community.css`; `public/js/hero.js`; `lang/{fr,en}/home|about|community.php` |
| **G · Tool pages** | `pages/gallery|generator|contact.blade.php`; `resources/views/errors/**`; `public/css/pages/gallery|generator|contact|errors.css`; `public/js/gallery.js`, `generator.js`, `contact.js`; `lang/{fr,en}/gallery|generator|contact|errors.php` |
| **H · Services templates** | `resources/views/services/index.blade.php`, `show.blade.php`, `resources/views/services/partials/**`; `public/css/pages/services.css`; `public/js/services.js`; `lang/{fr,en}/services.php` |
| **S · Service scenes** | per service: `resources/content/services/{slug}.php`, `resources/views/services/scenes/{slug}.blade.php`, `public/css/scenes/{slug}.css` |
| **Foundation** (done) | `config/atelier.php`, `public/css/01-tokens.css`, `resources/views/layouts/app.blade.php`, `app/helpers.php`, `bin/blade-lint.php`, `public/fonts/**`, `public/images/**`, favicons, this file |

`resources/content/animations.json` and `public/generated/**` are **generated** (by A's tools / B's command).

---

## 3. Routes, controllers & view data

French URLs (shared by both languages). All GET pages use the `web` middleware group.

| Method & URI | Name | Controller@action | View |
|---|---|---|---|
| GET `/` | `home` | `PageController@home` | `pages.home` |
| GET `/services` | `services.index` | `ServiceController@index` | `services.index` |
| GET `/services/{slug}` | `services.show` | `ServiceController@show` | `services.show` (404 for unknown slug) |
| GET `/a-propos` | `about` | `PageController@about` | `pages.about` |
| GET `/communaute` | `community` | `PageController@community` | `pages.community` |
| GET `/galerie` | `gallery` | `GalleryController@index` | `pages.gallery` |
| GET `/atelier-numerique` | `generator` | `GeneratorController@show` | `pages.generator` |
| GET `/atelier-numerique/oeuvre.svg` | `generator.art` | `GeneratorController@art` | SVG response (throttle 60/min) |
| GET `/mouvement` | `motion` | `PageController@motion` | `pages.motion` |
| GET `/contact` | `contact` | `ContactController@show` | `pages.contact` |
| POST `/contact` | `contact.store` | `ContactController@store` | redirect (throttle 6/min) |
| GET `/langue/{locale}` | `locale.switch` | `LocaleController` | redirect back |
| GET `/sitemap.xml` | `sitemap` | `SitemapController@index` | XML |
| GET `/robots.txt` | `robots` | `SitemapController@robots` | text |

### Data passed to views (exact variable names)

Service arrays are **localized** (see §4). `Collection` = `Illuminate\Support\Collection`.

| View | Variables |
|---|---|
| `pages.home` | `$services` (Collection, ordered), `$categories` (array key ⇒ label), `$galleryPreview` (array ≤ 8 artworks), `$animationCount` (int), `$artStyles` (string[] keys) |
| `services.index` | `$services`, `$categories` |
| `services.show` | `$service` (array), `$prev` (array), `$next` (array) — wrap around, `$related` (Collection, 3), `$inspirations` (array of 3 artworks) |
| `pages.about` | `$services`, `$categories`, `$animationCount` |
| `pages.community` | `$services`, `$categories` |
| `pages.gallery` | `$artworks` (array), `$styles` (string[] keys present) |
| `pages.generator` | `$styles` (string[]), `$defaultStyle` (`'pehouet'`), `$sizes` (int[]), `$artUrl` (string, base URL of `generator.art`) |
| `pages.motion` | `$animations` (array), `$groups` (array group ⇒ entries), `$total` (int), `$scenes` (Collection of services) |
| `pages.contact` | `$services`, `$selected` (slug or null — from `?service=`), `$budgets` (string[] keys) |
| `partials.header`, `partials.footer` (view composer) | `$navServices` (Collection), `$navCategories` (array key ⇒ label) |

**Artwork array** (gallery, inspirations, previews): `['src' => url, 'style' => key, 'seed' => string, 'title' => localized string, 'width' => int, 'height' => int]`.

**Animation entry** (motion page): `['n' => int, 'name' => 'ap-…', 'group' => …, 'demo' => …, 'file' => 'css/…', 'scene' => slug|null, 'title' => localized string, 'dur' => '2.4s', 'ease' => …, 'dir' => …, 'iter' => …]`.

### Contact form (POST `/contact`)
Fields: `name` (required, ≤120), `email` (required email, ≤190), `phone` (nullable, ≤40),
`service` (nullable, one of the service slugs), `budget` (nullable, one of `config('atelier.budgets')`),
`message` (required, 10–5000), `consent` (accepted), `website` (honeypot — must stay empty; if filled the
request is silently "accepted" and discarded). On success the controller stores a `ContactMessage`,
e-mails `config('atelier.contact.notify')` (failures are logged, never shown), and redirects to
`route('contact')` with `->with('status', 'sent')` and `#contact-form`. The view shows
`__('contact.flash.success')` when `session('status') === 'sent'`. Validation errors use the standard
`$errors` bag (`@error('field')`), messages from `lang/*/validation.php`.

### Art endpoint (GET `/atelier-numerique/oeuvre.svg`)
Query: `style` (one of `config('atelier.art_styles')`, default `pehouet`), `seed` (≤ 60 chars, default
`pehouet`), `size` (one of `config('atelier.art_engine.sizes')`, default 800), `animate` (0/1),
`download` (0/1 → `Content-Disposition: attachment; filename="pehouet-{style}-{slug}.svg"`).
Invalid input → HTTP 422 plain text. Response: `image/svg+xml; charset=utf-8`,
`Cache-Control: public, max-age=604800`, `X-Art-Engine: http|cli|fallback`, and a strict
`Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.

---

## 4. Services

22 art services (the site must always offer **at least 20**), each with **its own page** (`/services/{slug}`),
**its own content file** (`resources/content/services/{slug}.php`), **its own animated scene**
(`resources/views/services/scenes/{slug}.blade.php` + `public/css/scenes/{slug}.css`).

| # | slug | category | accent | icon | art_style | scene code |
|---|---|---|---|---|---|---|
| 1 | `peinture-murale` | peinture | red | roller | mondrian | mural |
| 2 | `tableaux-sur-commande` | peinture | yellow | easel | pehouet | tableau |
| 3 | `portraits` | peinture | amber | portrait | prisme | portrait |
| 4 | `graffiti-street-art` | peinture | blue | spray | eclats | graffiti |
| 5 | `design-graphique` | design | yellow | pen-tool | tissage | design |
| 6 | `enseignes-signaletique` | design | red | sign | soleil | enseigne |
| 7 | `calligraphie-lettrage` | design | white | nib | eclats | calli |
| 8 | `art-numerique` | design | blue | tablet | mosaique | numerique |
| 9 | `decoration-vehicules` | design | orange | van | eclats | vehicule |
| 10 | `sculpture` | matiere | blue | chisel | prisme | sculpt |
| 11 | `ceramique-poterie` | matiere | orange | vase | soleil | ceram |
| 12 | `mosaique-vitrail` | matiere | yellow | mosaic | vitrail | mosaic |
| 13 | `restauration-encadrement` | matiere | white | frame | mondrian | restau |
| 14 | `art-textile` | matiere | red | thread | tissage | textile |
| 15 | `decoration-interieure` | espaces | blue | sofa | tissage | deco |
| 16 | `decors-evenements` | espaces | red | stage | mosaique | event |
| 17 | `maquillage-artistique` | espaces | yellow | mask | soleil | maquillage |
| 18 | `photographie` | image | white | camera | vitrail | photo |
| 19 | `serigraphie-impression` | image | yellow | print | pehouet | serig |
| 20 | `illustration-bande-dessinee` | image | blue | book | prisme | bd |
| 21 | `cours-ateliers` | communaute | amber | pencil | pehouet | cours |
| 22 | `art-communautaire` | communaute | orange | hands | mosaique | commu |

Never hard-code the number of services in copy or code: count them (`$services->count()`).

Category labels: `__('ui.categories.{key}')` — peinture *Peinture & couleur / Painting & colour*,
design *Design & lettrage / Design & lettering*, matiere *Volume & matière / Form & material*,
espaces *Espaces & événements / Spaces & events*, image *Image & impression / Image & print*,
communaute *Transmission & communauté / Teaching & community*.

### Content file schema — `resources/content/services/{slug}.php`
```php
<?php

return [
    'slug' => 'peinture-murale',
    'order' => 1,
    'category' => 'peinture',
    'accent' => 'red',
    'icon' => 'roller',
    'art_style' => 'mondrian',
    'scene' => 'peinture-murale',            // = slug
    'fr' => [
        'title' => 'Peinture murale & fresques',      // ≤ 40 chars
        'short' => '…',                                // card one-liner, ≤ 110 chars
        'tagline' => '…',                              // hero tagline, ≤ 90 chars, poetic
        'intro' => '…',                                // ≈ 45–90 words
        'body' => ['…', '…'],                          // 2 paragraphs, ≈ 35–90 words each
        'features' => [['title' => '…', 'text' => '…'], /* exactly 6 */],
        'process' => [['title' => '…', 'text' => '…'], /* exactly 4 */],
        'ideal_for' => ['…', '…', '…', '…'],           // exactly 4 audiences
        'faq' => [['q' => '…', 'a' => '…'], /* exactly 4 */],
        'scene_alt' => '…',                            // describes the animated scene
        'meta_description' => '…',                     // 120–160 chars
    ],
    'en' => [ /* identical keys, natural English */ ],
];
```
Copy rules: warm, professional, community-minded, concrete about what the atelier does. **No invented
facts** — no client names, dates, years of experience, statistics, prices or testimonials. Pricing is
always "sur devis / on quote".

### `App\Support\ServiceCatalog` (B)
`all(?locale)`, `find(slug, ?locale)`, `slugs()`, `byCategory(?locale)`, `neighbors(slug)` (`['prev'=>…, 'next'=>…]`),
`related(slug, limit = 3)`. A **localized service array** contains: `slug, order, number ('01'…'22'),
category, category_label, accent, icon, art_style, scene, url` + the locale's content keys
(`title, short, tagline, intro, body, features, process, ideal_for, faq, scene_alt, meta_description`).
Missing English keys fall back to French.

### Scenes
- Root element: `<div class="scene scene--{slug} ap-anim-scope" role="img" aria-label="{{ $service['scene_alt'] }}">`.
  Everything inside is decorative (`aria-hidden="true"` on inner SVG).
- Rendered inside a square **stage** provided by the services template:
  `<div class="scene-stage accent-{accent}">` (`aspect-ratio: 1`, `container-type: inline-size`,
  `position: relative`, black background). Scenes fill it 100% × 100% and may use `cqi` units.
  The motion page embeds the same partials in smaller stages — scenes must scale from 220px to 640px.
- Prefer one inline `<svg viewBox="0 0 400 400">`; HTML/CSS 3D is fine where useful.
- CSS strictly scoped under `.scene--{slug}`; at least **3 unique keyframes** named
  `ap-sc-{code}-{what}` (scene code from the table), each with an `@anim` doc comment
  (`group=scene demo=scene scene={slug}`). SVG transforms need `transform-box: fill-box` +
  `transform-origin`.
- Use the service's accent prominently, plus the other logo colours. Make it a small artwork that
  tells what the service *is* (a roller painting a wall, a 3D pyramid being carved, a neon sign…).
- When motion is disabled the scene must still show a complete, beautiful composition.

---

## 5. Layout & partials

`resources/views/layouts/app.blade.php` is fixed (read it). It includes, in order:
`partials.jsonld` (head), `partials.loader`, `partials.header`, `<main id="main" class="site-main">`,
`partials.footer`, `partials.fx`, then scripts `head.js` (sync, in head) and deferred
`core.js → nav.js → fx.js → effects.js → loader.js → @stack('scripts')`.
Pages set: `@section('title')`, `@section('meta_description')`, `@section('body_class', 'page-x')`,
`@section('content')`, and `@push('styles')` / `@push('scripts')` (scripts **must** be `defer`).

Body classes: `page-home`, `page-services`, `page-service`, `page-about`, `page-community`,
`page-gallery`, `page-generator`, `page-motion`, `page-contact`, `page-error`.

Header: logo wordmark (links home), main nav — Services (mega menu with all the services grouped by
category), Galerie, Atelier numérique, Mouvement, Communauté, À propos — language switch
(FR/EN → `route('locale.switch', 'en')`), motion toggle, CTA button "Demander un devis" → `route('contact')`.
Mobile (< 1100px): burger → full-screen menu revealed by diagonal colour slices.
Use `aria-current="page"` on the active link (`request()->routeIs(...)`).

Footer: big statement + CTA, all the services by category, page links, contact (e-mail/phone/address
only when configured), socials (only when configured), motion toggle, language switch,
`© {year} Ateliers Pehouet — {tagline}`.

---

## 6. CSS architecture

Load order (global, every page): `01-tokens` → `02-base` → `03-layout` → `04-components` →
`05-anim-brand` → `06-anim-ambient` → `07-anim-ui`; then page sheets via `@push('styles')`:
`css/pages/{page}.css`, and on a service page `css/pages/services.css` + `css/scenes/{slug}.css`.
The motion page also loads every scene sheet.

Conventions: BEM-ish (`.block`, `.block__el`, `.block--mod`), state classes `.is-*`, JS hooks are
`data-*` attributes (never style by `data-*` hooks except the documented ones). Mobile-first; breakpoints
`40em`, `64em`, `80em`. No `!important` except in motion-reduction rules. Fluid sizes with `clamp()`.

### Global HTML-element state classes
`html.no-js|js`, `html.motion-off` (animations disabled), `html.motion-on` (user forced them on despite
`prefers-reduced-motion`), `html.is-loading` (intro loader visible), `html.menu-open`,
`html.reveal-ready` (effects.js initialised). `.is-paused` on an `.ap-anim-scope` pauses everything inside.

### Layout primitives (C · `03-layout.css`) — use these class names
`.container` (max `--ap-container`, side padding `--ap-gutter`), `.container--wide`, `.container--narrow`,
`.section` (block padding `--ap-section`), `.section--tight`, `.section--flush-top`, `.section--surface`
(coal background), `.section--slant` (diagonal top edge), `.grid` + `.grid--2|3|4|auto` (auto = auto-fill
minmax(16rem,1fr)), `.stack` (vertical flow, gap `--stack-gap`), `.cluster` (wrapping row),
`.split` (2 columns ≥ 64em, `.split--reverse`), `.sticky-aside`,
type: `.h-display`, `.h1`…`.h4`, `.eyebrow` (tagline font, caps, with a tri-colour mark), `.lead`,
`.muted`, `.gradient-text` (TELIERS gradient clipped to text), `.neon-text`, `.outline-text`
(transparent fill, white stroke), `.text-center`, `.visually-hidden`, `.skip-link`, `.prose`
(long-form text).

### Component classes (D · `04-components.css`)
`.btn` (+ `--primary` TELIERS gradient, `--secondary` blue, `--ghost`, `--outline`, `--light`, `--link`,
`--sm`, `--lg`, `__label`, `__icon`), `.icon`, `.chip` (+ `.is-active`), `.card`, `.service-card`,
`.feature`, `.steps` / `.step`, `.accordion` (`details`), `.breadcrumbs`, `.page-hero`, `.section-heading`,
`.cta-band`, `.counter`, `.marquee`, `.art-frame`, `.tri-divider`, `.lightbox`,
forms: `.form-grid`, `.field`, `.field__label`, `.field__input` (input/select/textarea), `.field__hint`,
`.field__error`, `.field--invalid`, `.choices` / `.choice` (radio/checkbox chips), `.checkbox`,
`.alert` (+ `--success`, `--error`).

---

## 7. Animation system (100+ animations)

Every animation is a named `@keyframes` in the site CSS. **Minimum totals per area** (the site needs ≥ 100;
we target ~170): C ≥ 22, D ≥ 40, E ≥ 24, F ≥ 12, G ≥ 10, H ≥ 6, each scene ≥ 3.

### Naming (prefix = owner, guarantees uniqueness)
| Prefix | File | Owner |
|---|---|---|
| `ap-brand-*`, `ap-logo-*`, `ap-sig-*`, `ap-nav-*`, `ap-load-*`, `ap-vt-*`, `ap-fx-*` | `05-anim-brand.css` | C |
| `ap-amb-*` | `06-anim-ambient.css` | E |
| `ap-ui-*`, `ap-rv-*` (reveals), `ap-tx-*` (text) | `07-anim-ui.css` | D |
| `ap-pg-{home,about,community,gallery,generator,contact,error,services,service}-*` | `pages/*.css` | F, G, H |
| `ap-mo-*` | `pages/motion.css` | E |
| `ap-sc-{code}-*` | `scenes/{slug}.css` | S |

### Mandatory doc comment (parsed by `python/tools/animations.py` to build the catalog)
Immediately before each `@keyframes`:
```css
/** @anim group=reveal demo=panel dur=1.1s ease=var(--ap-ease-out) dir=alternate fr="Révélation en triangle" en="Triangle reveal" */
@keyframes ap-rv-tri { … }
```
- `group` (required): `brand | text | reveal | ui | ambient | loader | transition | page | scene`
- `demo` (required): how the Motion page previews it —
  `shape` (a triangle-clipped gradient tile), `blocks` (5 Mondrian blocks, staggered via `--i`),
  `text` (the word "Pehouet"), `letters` (T-E-L-I-E-R-S spans, staggered), `path` (SVG triangle outline,
  `pathLength="1"`, for stroke-dash animations), `bar` (horizontal bar), `ring` (circle),
  `dots` (3 dots blue/yellow/red, staggered), `panel` (rectangle with a Mondrian background), `scene` (scene keyframes only)
- `fr`, `en` (required, quoted): human name of the animation.
- optional: `dur` (default `2.4s`), `ease` (default `ease-in-out`), `dir` (`normal|alternate|reverse`,
  default `normal`), `iter` (default `infinite`), `scene` (required for `demo=scene`: the service slug).
- Keyframes must work standalone: any custom property used inside needs a fallback, e.g.
  `translate: var(--dx, 40px) 0`.
- Every keyframe must be **used** on the site (referenced by an `animation`/`animation-name`
  declaration outside its definition, or in a Blade/JS file). Unused keyframes fail the audit.

### Motion rules
- Respect users: `02-base.css` (C) collapses all animations/transitions when `html.motion-off` is set, or
  under `prefers-reduced-motion: reduce` unless `html.motion-on`. A toggle (`[data-motion-toggle]`)
  lives in header & footer. JS effects must check `AP.motion()` and listen to `ap:motion`.
- With motion off, everything must end in a complete, visible state (no element stuck at opacity 0).
- Wrap continuously-animated regions (scenes, hero, ambient layers, marquees) in `.ap-anim-scope`:
  core.js pauses them off-screen and when the tab is hidden.
- Animate `transform`, `opacity`, `clip-path`, `stroke-dashoffset`, `background-position`, `filter`
  (sparingly). Never animate layout properties on large elements. No more than 3 flashes per second
  (WCAG 2.3.1); flicker effects stay subtle (opacity ≥ 0.55) and small.
- Reveal-on-scroll elements (`data-reveal`) are hidden only under `html.js` and only until revealed;
  `07-anim-ui.css` includes a fail-safe so content appears after 3 s if effects.js never initialises.

---

## 8. Blade components (anonymous, `resources/views/components/*.blade.php`)

Use `@props([...])` with defaults; merge `$attributes` on the root element; all strings via `__()`.

### 8.1 Brand (C)
- `<x-logo-mark :animated="false" :idle="false" />` — the triangle SVG (§1 geometry). `animated` ⇒ pieces fly in
  and assemble once; `idle` ⇒ subtle breathing loop. Root class `logo-mark`. `role="img"` +
  `aria-label` = brand name unless `decorative` attribute is passed (then `aria-hidden`).
- `<x-logo-wordmark size="header|hero|footer" :animated="false" :tagline="false" :signature="false" />` —
  mark + "TELIERS" (each letter a span with `--i`, continuous gradient across letters, neon halo) +
  optional tagline (`__('ui.tagline')`) and signature. Root `.wordmark .wordmark--{size}`.
- `<x-signature :animated="false" />` — SVG signature "Pehouet" with its long slashes; white strokes,
  `pathLength="1"` so it can draw itself. Root `.signature`.

### 8.2 Components (D)
| Component | Props (defaults) | Notes |
|---|---|---|
| `x-button` | `href=null, variant='primary', size='md', icon=null, iconBefore=null, magnetic=false, type='button', external=false` | `<a>` when `href`, else `<button>`; adds `data-ripple`; `magnetic` ⇒ `data-magnetic` |
| `x-icon` | `name, size=null` | inline 24×24 stroke SVG, `aria-hidden`. Must include every service icon (§4) and UI icons: `arrow-right arrow-left arrow-up arrow-down arrow-up-right chevron-down close menu mail phone map-pin clock whatsapp instagram facebook tiktok youtube globe download refresh dice play pause sparkle check plus minus quote eye palette triangle wave heart users calendar star external motion filter grid` |
| `x-section-heading` | `eyebrow=null, title, lead=null, align='left', level=2, accent='yellow'` | title split into words (`data-split="words"`) |
| `x-page-hero` | `eyebrow=null, title, lead=null, breadcrumbs=[], accent='red', compact=false` | default slot = actions; named slot `aside` = visual. Decorated with Mondrian blocks & triangles |
| `x-service-card` | `service, index=0, variant='default'` (`default|compact|feature`) | number, icon, title, short, category label, accent class, link, `data-tilt`, `data-reveal` |
| `x-breadcrumbs` | `items=[]` (`[['label'=>…,'url'=>…?], …]`) | home crumb added automatically |
| `x-tri-divider` | `variant='zigzag'` (`zigzag|peak|slope`), `flip=false` | decorative separator |
| `x-marquee` | `items=[], reverse=false, speed='normal'` | `data-marquee`, triangle separators |
| `x-counter` | `to, from=0, prefix='', suffix='', label, accent='yellow'` | `data-count-to` |
| `x-cta-band` | `title, text=null, href, button, secondaryHref=null, secondaryButton=null` | closing call-to-action |
| `x-art-frame` | `src, alt, caption=null, href=null, group=null, styleKey=null, lazy=true` | `href`+`group` ⇒ lightbox link |
| `x-feature` | `icon=null, title, text=null, index=0` | slot overrides text |
| `x-step` | `number, title, text=null` | used inside `<ol class="steps">` |
| `x-accordion-item` | `question, open=false` | slot = answer; `<details class="accordion">` |
| `x-chip` | `active=false, filter=null` | `<button class="chip" data-filter="…">` |

### 8.3 Ambient (E) — decorative, always `aria-hidden="true"`
| Component | Props | Notes |
|---|---|---|
| `x-floating-shapes` | `count=10, variant='mixed'` (`mixed|outline|solid`), `seed=1` | drifting triangles/blocks; deterministic positions from `seed` |
| `x-mondrian` | `variant='a'` (`a|b|c`), `animated=true` | a Mondrian composition whose fields shift |
| `x-aurora` | `intensity='normal'` | slow blue/yellow/red glows behind content |
| `x-starfield` | `count=40, seed=1` | twinkling white specks |
| `x-slashes` | `count=5` | signature-like white diagonal strokes drawing in |
| `x-orbit` | `size='m'` | small triangles orbiting a centre |
| `x-kaleidoscope` | `size='m'` | rotating triangle rosette in the palette |

---

## 9. JavaScript

Classic deferred scripts, each an IIFE, `'use strict'`. No inline scripts or `on*=` attributes (CSP).

### `window.AP` (C · `core.js`) — available to all later scripts
```js
AP.qs(sel, scope = document) → Element|null      AP.qsa(sel, scope = document) → Element[]
AP.ready(fn)                                       // DOM ready
AP.motion() → boolean                              // animations allowed?
AP.setMotion(enabled)                              // persists localStorage 'ap-motion' = 'on'|'off', updates html classes, emits 'ap:motion'
AP.onMotionChange(fn(enabled))
AP.inView(el, cb(isIntersecting, entry), {threshold = 0.15, rootMargin = '0px 0px -8% 0px', once = false} = {}) → stop()
AP.throttle(fn, ms) AP.debounce(fn, ms) AP.clamp(v, min, max) AP.lerp(a, b, t) AP.rand(min, max) AP.pick(arr)
AP.finePointer() → boolean                         // (hover: hover) and (pointer: fine)
AP.palette → { black, white, blue, yellow, red, amber, orange, smoke, blueBright, redBright }
AP.colors  → [blue, yellow, red, white, amber, orange]
AP.emit(name, detail) / AP.on(name, fn)            // CustomEvents on document, names 'ap:*'
```
core.js also: pauses `.ap-anim-scope` off-screen / when hidden (`.is-paused`), wires `[data-motion-toggle]`
(`aria-pressed`, label from `data-label-on`/`data-label-off` into its `[data-motion-label]`).

`head.js` (C): `no-js → js`; motion classes from `localStorage['ap-motion']` or `prefers-reduced-motion`;
adds `is-loading` on the first page view of the session when motion is allowed.
`nav.js` (C): header `.is-scrolled` / `.is-hidden`, mega menu, mobile menu (focus trap, Esc, `html.menu-open`).
`fx.js` (C): cursor follower & triangle trail (fine pointers only), scroll progress, back-to-top.
`loader.js` (C): dismiss intro loader (on `load`, max 2.2 s), `pageshow` safety.

### `effects.js` (D) — declarative `data-*` behaviours
| Attribute | Behaviour |
|---|---|
| `data-reveal="fade-up"` (+`data-reveal-delay="ms"`) | adds `.is-revealed` when visible. Types: `fade-up fade-down fade-left fade-right zoom-in zoom-out flip-x flip-y tri diagonal curtain rotate blur skew mondrian rise drop` |
| `data-reveal-stagger="80"` | on a parent: incremental delays for `[data-reveal]` children |
| `data-split="chars|words"` (+`data-split-anim="rise|flip|glow|wave|drop|neon"`) | wraps units in `.split__unit` with `--i`; animates when visible; keeps an accessible label |
| `data-count-to="22"` (+`data-count-from`, `-duration`, `-prefix`, `-suffix`) | animated counter |
| `data-tilt` (+`data-tilt-max="10"`) | 3D tilt following the pointer |
| `data-magnetic` (+`data-magnetic-strength`) | element drifts toward the pointer |
| `data-ripple` | triangle ripple on click |
| `data-parallax="0.15"` | scroll parallax (translateY) |
| `data-marquee` | duplicates the track for a seamless loop |
| `data-typewriter='["…","…"]'` | types/deletes the words in turn |
| `data-burst` | triangle confetti from the click point |
| `data-glitch` | RGB-split (blue/red) glitch on hover; copies text into `data-text` |
| `data-spotlight` | sets `--mx`/`--my` on pointer move for radial light effects |
| `data-lightbox="group"` on `<a href="image">` | accessible lightbox with prev/next, Esc, focus return |
| `data-filter-group` + `[data-filter]` buttons + `[data-filter-item][data-category="a b"]` | animated filtering, `aria-pressed`, `.is-hidden` on items |
| `details.accordion` | smooth open/close |

Page scripts (`hero.js`, `gallery.js`, `generator.js`, `contact.js`, `services.js`, `motion.js`) only add
page-specific behaviour and must degrade gracefully without JS.

---

## 10. Internationalisation

**French is the main language; English is the secondary translation.** Every visitor gets French
unless they choose English (no `Accept-Language` auto-detection). URLs/slugs are French for both
languages. Clean URLs are the French canonical + `hreflang="x-default"`; the English version of any
page is the same URL with `?lang=en` (canonical/hreflang tags in the layout). French copy is written
first; English must be complete, natural and never fall back to raw keys. E-mails sent to the atelier
(quote requests) are always in French; they mention the language the visitor used.

- `App\Http\Middleware\SetLocale` (B) applies `session('locale')` (or `?lang=fr|en`) on every web request.
- PHP array files per area: `lang/{fr,en}/{ui,components,home,about,community,services,gallery,generator,contact,errors,motion,validation,mail}.php`.
  **fr and en files must have identical key structures** (a test enforces it). Keys snake_case, nested by section.
- Required shared keys in `ui.php` (C), usable by everyone:
  `ui.tagline`, `ui.meta.description`, `ui.a11y.{skip,menu_open,menu_close,back_to_top,home,main_nav,language,new_tab}`,
  `ui.nav.{home,services,all_services,gallery,generator,motion,about,community,contact}`,
  `ui.cta.{quote,discover,services,create,contact,learn_more,back_home,see_all}`,
  `ui.categories.{peinture,design,matiere,espaces,image,communaute}`,
  `ui.motion.{label,on,off,toggle}`, `ui.lang.{fr,en,switch}`,
  `ui.footer.{statement,text,services,explore,contact,follow,rights}`, `ui.loader.label`, `ui.breadcrumb.home`.
- Generated-art style names: `__('generator.styles.{key}.name')` / `.desc` (G) — used by gallery, services, home.

---

## 11. Python art engine (A)

```
python/
  art_engine/__init__.py  __main__.py  palette.py  rng.py  svg.py  styles/{pehouet,mondrian,prisme,mosaique,vitrail,eclats,soleil,tissage}.py
              registry.py  titles.py  server.py  cli.py
  tools/animations.py  palette_audit.py
  tests/test_*.py         (unittest, stdlib only)
```
Run from `python/` (`cd python && python3 -m art_engine …`) or with `PYTHONPATH=python`.

- **Styles** (deterministic per `(style, seed, width, height, animate)`, byte-identical output):
  `pehouet` (the logo triangle recomposed into new Mondrian partitions), `mondrian` (recursive rectangular
  grid, black/white seams), `prisme` (recursive triangle subdivision), `mosaique` (triangular lattice tiles),
  `vitrail` (stained glass: lead lines + translucent panes + light), `eclats` (signature-like slashes and
  shards), `soleil` (radiating triangles / sunburst), `tissage` (woven diagonal bands).
- Palette from `palette.py` (mirrors §1). Seeds hashed with SHA-256 (never Python's `hash()`).
- SVG: valid XML, `viewBox`, `width`/`height`, `<title>` (escaped), no scripts/external refs. `animate`
  adds an embedded `<style>` with keyframes (pieces appearing, slow drift) that respects
  `prefers-reduced-motion`.
- **CLI**: `render --style S --seed X [--width 800] [--height 800] [--animate]` → SVG on stdout;
  `styles [--json]`; `serve [--host 127.0.0.1] [--port 8765]`;
  `batch --spec spec.json --out DIR` where spec = `{"items":[{"file":"services/x-1.svg","style":"…","seed":"…","width":800,"height":800,"animate":false}]}`
  (paths must stay inside DIR) → prints `{"written": n, "files": [...]}`;
  `gallery --out DIR [--per-style 4] [--size 800]` → `DIR/gallery/{style}-{n}.svg` +
  `DIR/gallery/manifest.json` = `{"version":1,"items":[{"file":"gallery/pehouet-1.svg","style":"pehouet","seed":"…","title":{"fr":"…","en":"…"},"width":800,"height":800}]}`.
  Invalid arguments → exit code 2 with a message on stderr.
- **HTTP** (`ThreadingHTTPServer`, default 127.0.0.1:8765): `GET /health` →
  `{"status":"ok","engine":"art_engine","version":"…","styles":[…]}`; `GET /styles`; `GET /palette`;
  `GET /art?style=&seed=&width=&height=&animate=0|1` → `image/svg+xml`; errors → JSON `{"error": …}` with
  400/404. Width/height 64–2400, seed ≤ 200 chars. Small in-memory LRU cache.
- **Laravel bridge** `App\Services\ArtEngine::render(string $style, string $seed, int $size = 800, bool $animate = false): array`
  → `['svg' => string, 'source' => 'http'|'cli'|'fallback']`. Tries HTTP (if `art_engine.url`), then the CLI
  via `Illuminate\Support\Facades\Process` (if `art_engine.cli`), then a built-in PHP SVG. Never throws.
- `php artisan atelier:generate-art` (B) writes `public/generated/gallery/*` (gallery mode) and
  `public/generated/services/{slug}-{1..3}.svg` (batch mode, seed `{slug}-{n}`, the service's `art_style`).
- `php artisan atelier:serve` (B) runs `php artisan serve` and the Python service together.

### QA tools
- `python3 python/tools/animations.py [--check]` — parses every `@keyframes` + `@anim` comment in
  `public/css/**/*.css`, validates (prefix, uniqueness, doc comment, group/demo values, usage), numbers them
  (group order above, then file, then position) and writes `resources/content/animations.json`
  (`{"version":1,"total":N,"groups":[…],"animations":[{"n","name","group","demo","file","scene","label":{"fr","en"},"dur","ease","dir","iter"}]}`).
  `--check` fails (exit 1) on any problem, fewer than 100 animations, or a stale JSON.
- `python3 python/tools/palette_audit.py` — fails on any colour literal outside the palette in CSS, Blade, JS.

---

## 12. Security, accessibility, performance

- `App\Http\Middleware\SecurityHeaders` (B): `X-Content-Type-Options: nosniff`, `Referrer-Policy:
  strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN`, `Permissions-Policy`, and CSP
  `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:;
  font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';
  frame-ancestors 'self'` (report-only when `APP_DEBUG=true`; never overrides a CSP already set).
- Escape everything (`{{ }}`); `{!! !!}` only for trusted, generated SVG.
- Semantic landmarks, one `<h1>` per page, visible `:focus-visible` (yellow), keyboard-operable menus,
  lightbox and filters, `alt` text, labels on every field, colour contrast AA.
- Images `loading="lazy"` + `decoding="async"` below the fold; explicit `width`/`height`.

## 13. Verification commands
```bash
php bin/blade-lint.php resources/views          # compile + lint Blade
php artisan test                                 # PHPUnit (B)
cd python && python3 -m unittest discover -s tests -t .   # Python tests (A)
python3 python/tools/animations.py --check       # ≥ 100 animations, catalog fresh
python3 python/tools/palette_audit.py            # logo colours only
php artisan serve  # then open http://127.0.0.1:8000
```

## 14. Admin CMS (`/admin`) — summary of `docs/CMS.md`

The owner edits the site at **`/admin`** without code: every text of `lang/{fr,en}` (editable groups), every
field of the services (and creates new ones), photos (library, gallery, service covers and "Réalisations", page
photo spots), free pages (`/{slug}`, e.g. legal notice), contact requests (mini CRM), settings (contact, socials,
announcement banner), accounts and maintenance. **`docs/CMS.md` is the binding contract** (its §13 amendments
override its earlier sections); this section only lists what every area must know.

- **Files stay the defaults.** `lang/*` and `resources/content/services/*.php` are never written by the CMS: the
  database stores *overrides* (`translation_overrides`, `cms_services`, `settings`) and what the owner creates
  (custom services, free pages, photos). `__()` and `ServiceCatalog` read files first, then apply overrides from
  `App\Cms\Cms`, a cached snapshot. If the database is unreachable or the CMS tables are missing, the public site
  renders the file defaults (circuit breaker `App\Cms\DatabaseHealth`, stale-if-error snapshot) — never a 500.
- **Keep editing lang files normally:** new keys become editable automatically; overrides of removed keys are
  ignored. Never rename a key casually — its override would be orphaned.
- **Photos** live in the database (`media`, `media_files`, base64) and are served by `GET /media/{key}`; render them
  with `App\Cms\MediaItem` (`url()`, `srcset()`, `alt()`), never with raw paths. Public photo partials render nothing
  when there is no photo.
- **Public templates** include the CMS partials listed in `docs/CMS.md` §12 (photos, announcement, footer pages,
  preview banner, admin bar, generic scene for custom services).
- **Admin conventions**: admin views use `admin.layouts.app` and `<x-admin.*>` components, CSS in
  `public/css/admin/`, JS in `public/js/admin/` (CSP: no inline scripts), copy in `lang/*/admin*.php`; invalid forms are
  re-rendered with HTTP 422 (`RendersInvalidForms`), never flashed into the 4 KB cookie session (Vercel).
- **Postgres in production** (Supabase, emulated prepares): booleans only as PHP bools, strings cut to column size,
  case-insensitive search with `whereLike(..., caseSensitive: false)`.
- **Deploying a CMS change**: new migrations are applied from *Admin → Maintenance → Mettre à jour la base de
  données* (Vercel has no shell). `php artisan atelier:admin {email}` creates an administrator on the Codespace.
