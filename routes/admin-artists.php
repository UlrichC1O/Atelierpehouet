<?php

use App\Http\Controllers\Admin\ArtistController;
use App\Http\Controllers\Admin\ArtworkController;
use App\Http\Controllers\Admin\ExhibitionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — artist pages in the admin (docs/ARTISTS.md §6)
|--------------------------------------------------------------------------
|
| Required by routes/admin.php INSIDE the CMS group that already adds the
| "admin" prefix, the "admin." name prefix and the web, admin.headers, auth
| and cms.ready middleware: relative URIs and short names here. The
| controllers add App\Artists\Http\EnsureArtistTables themselves.
| {artist}, {artwork}, {exhibition}: model ids; works and exhibitions are
| looked up among the artist's own (scoped bindings).
|
*/

Route::get('/artistes', [ArtistController::class, 'index'])->name('artists.index');
Route::post('/artistes/ordre', [ArtistController::class, 'reorder'])->name('artists.reorder');
Route::post('/artistes/exemple', [ArtistController::class, 'example'])->name('artists.example');
Route::get('/artistes/creer', [ArtistController::class, 'create'])->name('artists.create');
Route::post('/artistes', [ArtistController::class, 'store'])->name('artists.store');

Route::whereNumber(['artist', 'artwork', 'exhibition'])->scopeBindings()->group(function (): void {
    Route::get('/artistes/{artist}', [ArtistController::class, 'edit'])->name('artists.edit');
    Route::put('/artistes/{artist}', [ArtistController::class, 'update'])->name('artists.update');
    Route::post('/artistes/{artist}/visibilite', [ArtistController::class, 'toggle'])->name('artists.toggle');
    Route::post('/artistes/{artist}/portrait', [ArtistController::class, 'portrait'])->name('artists.portrait');
    Route::delete('/artistes/{artist}', [ArtistController::class, 'destroy'])->name('artists.destroy');

    Route::get('/artistes/{artist}/oeuvres', [ArtworkController::class, 'index'])->name('artists.artworks.index');
    Route::post('/artistes/{artist}/oeuvres', [ArtworkController::class, 'store'])->name('artists.artworks.store');
    Route::post('/artistes/{artist}/oeuvres/ordre', [ArtworkController::class, 'reorder'])->name('artists.artworks.reorder');
    Route::get('/artistes/{artist}/oeuvres/{artwork}', [ArtworkController::class, 'edit'])->name('artists.artworks.edit');
    Route::put('/artistes/{artist}/oeuvres/{artwork}', [ArtworkController::class, 'update'])->name('artists.artworks.update');
    Route::post('/artistes/{artist}/oeuvres/{artwork}/image', [ArtworkController::class, 'image'])->name('artists.artworks.image');
    Route::delete('/artistes/{artist}/oeuvres/{artwork}', [ArtworkController::class, 'destroy'])->name('artists.artworks.destroy');

    Route::get('/artistes/{artist}/expositions', [ExhibitionController::class, 'index'])->name('artists.exhibitions.index');
    Route::get('/artistes/{artist}/expositions/creer', [ExhibitionController::class, 'create'])->name('artists.exhibitions.create');
    Route::post('/artistes/{artist}/expositions', [ExhibitionController::class, 'store'])->name('artists.exhibitions.store');
    Route::get('/artistes/{artist}/expositions/{exhibition}', [ExhibitionController::class, 'edit'])->name('artists.exhibitions.edit');
    Route::put('/artistes/{artist}/expositions/{exhibition}', [ExhibitionController::class, 'update'])->name('artists.exhibitions.update');
    Route::post('/artistes/{artist}/expositions/{exhibition}/image', [ExhibitionController::class, 'image'])->name('artists.exhibitions.image');
    Route::delete('/artistes/{artist}/expositions/{exhibition}', [ExhibitionController::class, 'destroy'])->name('artists.exhibitions.destroy');
});
