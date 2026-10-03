<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Contact requests, the mini CRM (docs/CMS.md §7.2), built in phase 2 by admin-shell.
 * Until then every action answers "coming soon" (ComingSoon): a page for GET, an error flash
 * otherwise — never a 501 for the owner.
 */
final class MessageController extends Controller
{
    use ComingSoon;

    public function index(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'messages');
    }

    public function show(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'messages');
    }

    public function update(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'messages');
    }

    public function destroy(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'messages');
    }
}
