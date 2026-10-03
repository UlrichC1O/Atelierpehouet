<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Placeholder of an admin section still being built (docs/CMS.md phase 2): the owner may sign in on
 * production before every screen exists, so no admin URL answers 501. A GET shows the "coming soon"
 * page (admin layout, HTTP 200, link back to the dashboard); any other method goes back with an
 * error flash (JSON requests: 503 {message}).
 *
 * Each phase-2 controller drops this trait when it gets its real actions; once none uses it, the
 * trait and admin/coming-soon.blade.php can go.
 */
trait ComingSoon
{
    /**
     * @param  string  $section  the section's key: its label is admin.nav.{section}
     */
    protected function comingSoon(Request $request, string $section): Response|RedirectResponse|JsonResponse
    {
        if ($request->isMethodSafe()) {
            return response()->view('admin.coming-soon', ['section' => $section]);
        }

        $message = __('admin.coming_soon.unavailable');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Back to the admin page the form was on (never another site), else the dashboard.
        $previous = url()->previous(route('admin.dashboard'));
        $admin = route('admin.dashboard');
        $back = $previous === $admin || str_starts_with($previous, $admin.'/') || str_starts_with($previous, $admin.'?')
            ? $previous
            : $admin;

        return redirect()->to($back)->with('error', $message);
    }
}
