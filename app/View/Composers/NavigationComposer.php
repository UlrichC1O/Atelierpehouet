<?php

namespace App\View\Composers;

use App\Support\ServiceCatalog;
use Illuminate\View\View;

/**
 * Header & footer navigation data (docs/ARCHITECTURE.md §3):
 *   $navServices   Collection of localized services, ordered (empty when none exist)
 *   $navCategories array category key ⇒ localized label, in display order
 */
final class NavigationComposer
{
    public function __construct(private readonly ServiceCatalog $catalog) {}

    public function compose(View $view): void
    {
        $view->with([
            'navServices' => $this->catalog->all(),
            'navCategories' => $this->catalog->categories(),
        ]);
    }
}
