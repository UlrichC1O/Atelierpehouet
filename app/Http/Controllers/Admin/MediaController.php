<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Photo library (docs/CMS.md §7.6), built in phase 2 by admin-media.
 * Until then every action answers "coming soon" (ComingSoon): a page for GET, an error flash
 * otherwise — never a 501 for the owner.
 */
final class MediaController extends Controller
{
    use ComingSoon;

    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }

    public function store(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }

    public function edit(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }

    public function update(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }

    public function replace(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }

    public function destroy(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'media');
    }
}
