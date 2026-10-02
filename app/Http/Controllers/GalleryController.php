<?php

namespace App\Http\Controllers;

use App\Support\Gallery;
use Illuminate\Contracts\View\View;

/**
 * The generative-art gallery.
 */
final class GalleryController extends Controller
{
    public function index(Gallery $gallery): View
    {
        return view('pages.gallery', [
            'artworks' => $gallery->all(),
            'styles' => $gallery->styles(),
        ]);
    }
}
