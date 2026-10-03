<?php

namespace App\Http\Controllers;

/**
 * Free pages created in the CMS (docs/CMS.md §7.4). Stub until the admin-content agent
 * implements it: every slug is unknown, so the visitor gets the normal 404 page.
 */
final class CustomPageController extends Controller
{
    public function show(string $slug): never
    {
        abort(404);
    }
}
