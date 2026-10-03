<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Textes des pages (docs/CMS.md §7.3), built in phase 2 by admin-content.
 * Until then every action answers "coming soon" (ComingSoon): a page for GET, an error flash
 * otherwise — never a 501 for the owner.
 */
final class TextController extends Controller
{
    use ComingSoon;

    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'texts');
    }

    public function edit(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'texts');
    }

    public function update(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'texts');
    }
}
