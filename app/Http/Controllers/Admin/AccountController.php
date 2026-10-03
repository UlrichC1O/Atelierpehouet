<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Account & administrators (docs/CMS.md §7.2), built in phase 2 by admin-shell.
 * Until then every action answers "coming soon" (ComingSoon): a page for GET, an error flash
 * otherwise — never a 501 for the owner.
 */
final class AccountController extends Controller
{
    use ComingSoon;

    public function edit(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'account');
    }

    public function update(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'account');
    }

    public function storeUser(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'account');
    }

    public function destroyUser(Request $request): Response|RedirectResponse|JsonResponse
    {
        return $this->comingSoon($request, 'account');
    }
}
