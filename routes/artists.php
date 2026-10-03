<?php

use App\Http\Controllers\ArtistController;
use App\Models\Artist;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ateliers Pehouet — public artist pages (docs/ARTISTS.md §5)
|--------------------------------------------------------------------------
|
| Required at the top level of routes/web.php ("web" group), before the
| free-page catch-all GET /{slug}.
|
*/

Route::get('/artistes', [ArtistController::class, 'index'])->name('artists.index');

Route::get('/artistes/{slug}', [ArtistController::class, 'show'])
    ->where('slug', Artist::SLUG_PATTERN)
    ->name('artists.show');
