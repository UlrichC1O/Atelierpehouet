<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GeneratorController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
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

// Machine-facing endpoints (artwork images, sitemap, robots) skip the session:
// no session row or cookie per image, and they never become the "previous URL".
$stateless = [StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class];

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [ServiceController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
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

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->withoutMiddleware($stateless)->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->withoutMiddleware($stateless)->name('robots');

// Unknown URLs still run through the "web" group, so the 404 page gets the
// visitor's session, language and the header/footer navigation.
Route::fallback(fn () => abort(404));
