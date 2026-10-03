<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomPageController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\ApplyCms;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — web routes (docs/ARCHITECTURE.md §3)
|--------------------------------------------------------------------------
|
| French URLs shared by both languages; the locale lives in the session
| (see App\Http\Middleware\SetLocale). Every route runs in the "web" group.
|
*/

// Machine-facing endpoints (artwork images, photos, sitemap, robots) skip the session — no session
// row or cookie per image, and they never become the "previous URL" — and ApplyCms (docs/CMS.md §13 A5).
$stateless = [StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class, ApplyCms::class];

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
// An unknown slug while the CMS data cannot be read answers 503, not 404 (cms.data, docs/CMS.md §13 A3).
Route::get('/services/{slug}', [ServiceController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('cms.data')
    ->name('services.show');

Route::get('/a-propos', [PageController::class, 'about'])->name('about');
Route::get('/communaute', [PageController::class, 'community'])->name('community');
Route::get('/galerie', [GalleryController::class, 'index'])->name('gallery');

Route::get('/atelier-numerique', [GeneratorController::class, 'show'])->name('generator');
Route::get('/atelier-numerique/oeuvre.svg', [GeneratorController::class, 'art'])
    ->withoutMiddleware($stateless)
    ->middleware('throttle:60,1')
    ->name('generator.art');

Route::get('/mouvement', [PageController::class, 'motion'])->name('motion');

Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('contact.store');

Route::get('/langue/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->withoutMiddleware($stateless)->middleware('cms.data:always')->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->withoutMiddleware($stateless)->name('robots');

// Photos of the CMS library (docs/CMS.md §4.5): immutable URLs, cached by browsers and the CDN —
// no throttle, the CDN in front of them asks once per photo and region (docs/CMS.md §13 A5).
Route::get('/media/{key}', MediaFileController::class)
    ->where('key', '[0-9a-z]{26}(?:-[0-9]{2,4})?\.(?:jpg|png|webp|gif)')
    ->withoutMiddleware($stateless)
    ->name('media.show');

// Artist pages (docs/ARTISTS.md, built by the "artists" session): /artistes, /artistes/{slug}.
if (is_file(__DIR__.'/artists.php')) {
    require __DIR__.'/artists.php';
}

// The admin CMS (docs/CMS.md §5) — registered before the free-page catch-all below.
Route::prefix('admin')->name('admin.')->middleware('admin.headers')->group(base_path('routes/admin.php'));

// Free pages created in the CMS (e.g. /mentions-legales): the last explicit route, unknown slugs ⇒ 404
// (503 while the CMS data cannot be read, cms.data).
Route::get('/{slug}', [CustomPageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->middleware('cms.data')
    ->name('pages.custom');

// Unknown URLs still run through the "web" group, so the 404 page gets the
// visitor's session, language and the header/footer navigation.
Route::fallback(fn () => abort(404));
