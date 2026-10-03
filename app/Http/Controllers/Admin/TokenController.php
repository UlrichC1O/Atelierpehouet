<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /admin/jeton (admin.token, docs/CMS.md §13 D20): the session's current CSRF token, which
 * admin.js puts back into the forms of a page left open for a long time (or after a 419), so a
 * long edit is not lost to an expired token. Never cached.
 */
final class TokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private');
    }
}
