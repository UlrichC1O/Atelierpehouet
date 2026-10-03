<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SlotController;
use App\Http\Controllers\Admin\TextController;
use App\Http\Controllers\Admin\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — admin CMS routes (docs/CMS.md §5)
|--------------------------------------------------------------------------
|
| Loaded by routes/web.php inside the "web" group with the "admin" prefix,
| the "admin." name prefix and the admin.headers middleware, BEFORE the
| free-page catch-all GET /{slug}.
|
| Guests: sign-in and password reset. Signed-in users: "auth" + "auth.session"
| (a password change signs the other devices out, docs/CMS.md §13 D17), then
| "can:admin" (the explicit admin right, §13 D18) on everything but logout,
| then "cms.ready" (every CMS migration ran) on everything but maintenance.
|
*/

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [AuthController::class, 'show'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->name('login.attempt');

    // Password reset by e-mail (docs/CMS.md §13 D19).
    Route::get('/mot-de-passe-oublie', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordController::class, 'email'])->name('password.email');
    Route::get('/reinitialiser/{token}', [PasswordController::class, 'reset'])->where('token', '[A-Za-z0-9]+')->name('password.reset');
    Route::post('/reinitialiser/{token}', [PasswordController::class, 'update'])->where('token', '[A-Za-z0-9]+')->name('password.update');
});

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('can:admin')->group(function (): void {
        // The session's CSRF token for admin.js, which refreshes the forms of a long-open page (§13 D20).
        Route::get('/jeton', TokenController::class)->name('token');

        // Design-system reference of the admin UI (every component and class, in every state).
        Route::view('/guide', 'admin.guide')->name('guide');

        // Usable while the CMS tables are missing: this is where the database gets updated.
        Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance');
        Route::post('/maintenance/base', [MaintenanceController::class, 'migrate'])->name('maintenance.migrate');
        Route::post('/maintenance/cache', [MaintenanceController::class, 'flush'])->name('maintenance.flush');
        Route::get('/maintenance/export', [MaintenanceController::class, 'export'])->name('maintenance.export');
    });

    Route::middleware(['can:admin', 'cms.ready'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/textes', [TextController::class, 'index'])->name('texts.index');
        Route::get('/textes/{group}', [TextController::class, 'edit'])->where('group', '[a-z_]+')->name('texts.edit');
        Route::put('/textes/{group}', [TextController::class, 'update'])->where('group', '[a-z_]+')->name('texts.update');

        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('/services/ordre', [ServiceController::class, 'reorder'])->name('services.reorder');
        Route::get('/services/creer', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{slug}', [ServiceController::class, 'edit'])->where('slug', '[a-z0-9-]+')->name('services.edit');
        Route::put('/services/{slug}', [ServiceController::class, 'update'])->where('slug', '[a-z0-9-]+')->name('services.update');
        Route::post('/services/{slug}/visibilite', [ServiceController::class, 'toggle'])->where('slug', '[a-z0-9-]+')->name('services.toggle');
        Route::delete('/services/{slug}/personnalisation', [ServiceController::class, 'reset'])->where('slug', '[a-z0-9-]+')->name('services.reset');
        Route::delete('/services/{slug}', [ServiceController::class, 'destroy'])->where('slug', '[a-z0-9-]+')->name('services.destroy');

        Route::get('/photos', [MediaController::class, 'index'])->name('media.index');
        Route::post('/photos', [MediaController::class, 'store'])->name('media.store');
        Route::get('/photos/{media}', [MediaController::class, 'edit'])->whereNumber('media')->name('media.edit');
        Route::put('/photos/{media}', [MediaController::class, 'update'])->whereNumber('media')->name('media.update');
        Route::post('/photos/{media}/remplacer', [MediaController::class, 'replace'])->whereNumber('media')->name('media.replace');
        Route::delete('/photos/{media}', [MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');

        Route::get('/galerie', [GalleryController::class, 'index'])->name('gallery.index');
        Route::post('/galerie/ordre', [GalleryController::class, 'reorder'])->name('gallery.reorder');

        Route::post('/emplacements', [SlotController::class, 'update'])->name('slots.update');

        Route::get('/pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('/pages/creer', [PageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}', [PageController::class, 'edit'])->whereNumber('page')->name('pages.edit');
        Route::put('/pages/{page}', [PageController::class, 'update'])->whereNumber('page')->name('pages.update');
        Route::delete('/pages/{page}', [PageController::class, 'destroy'])->whereNumber('page')->name('pages.destroy');

        Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [MessageController::class, 'show'])->whereNumber('message')->name('messages.show');
        Route::put('/messages/{message}', [MessageController::class, 'update'])->whereNumber('message')->name('messages.update');
        Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->whereNumber('message')->name('messages.destroy');

        Route::get('/reglages', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/reglages', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('/compte', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('/compte', [AccountController::class, 'update'])->name('account.update');
        Route::post('/compte/administrateurs', [AccountController::class, 'storeUser'])->name('users.store');
        Route::delete('/compte/administrateurs/{user}', [AccountController::class, 'destroyUser'])->whereNumber('user')->name('users.destroy');

        // Artist pages admin (docs/ARTISTS.md): relative URIs (/artistes…) and names (artists.*) — this
        // group already adds the "admin" prefix, the "admin." name prefix, auth, auth.session, can:admin
        // and cms.ready.
        if (is_file(__DIR__.'/admin-artists.php')) {
            require __DIR__.'/admin-artists.php';
        }
    });
});
