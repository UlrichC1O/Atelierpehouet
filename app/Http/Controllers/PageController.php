<?php

namespace App\Http\Controllers;

use App\Support\AnimationCatalog;
use App\Support\Gallery;
use App\Support\ServiceCatalog;
use Illuminate\Contracts\View\View;

/**
 * Story pages: home, about, community and the motion (animation) gallery.
 */
final class PageController extends Controller
{
    /** Artworks shown in the home page's gallery teaser. */
    private const GALLERY_PREVIEW = 8;

    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function home(Gallery $gallery, AnimationCatalog $animations): View
    {
        return view('pages.home', [
            'services' => $this->catalog->all(),
            'categories' => $this->catalog->categories(),
            'galleryPreview' => $gallery->preview(self::GALLERY_PREVIEW),
            'animationCount' => $animations->total(),
            'artStyles' => array_values((array) config('atelier.art_styles', [])),
        ]);
    }

    public function about(AnimationCatalog $animations): View
    {
        return view('pages.about', [
            'services' => $this->catalog->all(),
            'categories' => $this->catalog->categories(),
            'animationCount' => $animations->total(),
        ]);
    }

    public function community(): View
    {
        return view('pages.community', [
            'services' => $this->catalog->all(),
            'categories' => $this->catalog->categories(),
        ]);
    }

    public function motion(AnimationCatalog $animations): View
    {
        return view('pages.motion', [
            'animations' => $animations->all(),
            'groups' => $animations->grouped(),
            'total' => $animations->total(),
            // Services created in the CMS have no scene of their own (they use the generic one).
            'scenes' => $this->catalog->all()->reject(fn (array $service): bool => $service['custom'])->values(),
        ]);
    }
}
