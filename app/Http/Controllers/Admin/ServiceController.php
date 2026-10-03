<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Services (docs/CMS.md §7.5), built in phase 2 by admin-services.
 * Until then every action answers "coming soon" (ComingSoon): a page for GET, an error flash
 * otherwise — never a 501 for the owner.
 */
final class ServiceController extends Controller
{
    use ComingSoon;

    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function reorder(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function create(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function store(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function edit(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function update(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function toggle(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function reset(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }

    public function destroy(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'services');
    }
}
