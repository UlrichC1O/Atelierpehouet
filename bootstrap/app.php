<?php

use App\Cms\AdminErrors;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Middleware\AdminHeaders;
use App\Http\Middleware\ApplyCms;
use App\Http\Middleware\EnsureCmsData;
use App\Http\Middleware\EnsureCmsReady;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SetLocale needs the session, so it runs after StartSession (end of the group).
        // ApplyCms (docs/CMS.md §4.6) runs after it: fresh CMS data for admins, site settings into config.
        $middleware->web(append: [
            SetLocale::class,
            ApplyCms::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin.headers' => AdminHeaders::class,
            'cms.ready' => EnsureCmsReady::class,
            'cms.data' => EnsureCmsData::class,
        ]);

        // Admin headers (noindex, no-store) also on the answers of the auth middleware (the guest
        // redirect to the login), and the CMS schema check before any route model binding queries a
        // table that may not exist yet (docs/CMS.md §13 E23).
        $middleware->prependToPriorityList(AuthenticatesRequests::class, AdminHeaders::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCmsReady::class);

        // The only login of the site is the admin CMS.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An expired session (419) on an admin URL: the admin's own page, the form's changes kept
        // in the browser (docs/CMS.md §13 D20).
        $exceptions->render(fn (HttpException $e, Request $request) => $e->getStatusCode() === 419 && $request->is('admin', 'admin/*')
            ? app(AuthController::class)->expired($request)
            : null);

        // A contact message (up to 5000 characters) would overflow the 4 KB cookie session
        // when flashed back with errors; public/js/contact.js keeps the draft instead.
        $exceptions->dontFlash('message');
        // Same safety net for the long texts of the admin forms (docs/CMS.md §13 E21).
        $exceptions->dontFlash(AdminErrors::DONT_FLASH);

        // A signed-in account without the admin right (Gate "admin", docs/CMS.md §13 D18): the
        // admin's own 403 page, with a way to sign out.
        $exceptions->render(fn (HttpException $e, Request $request) => $e->getStatusCode() === 403 && AdminErrors::isAdmin($request)
            ? AdminErrors::forbidden($request)
            : null);

        // A request over the server's size limit on an admin URL: a French 413 (docs/CMS.md §13 C10).
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => AdminErrors::isAdmin($request)
            ? AdminErrors::tooLarge($request)
            : null);

        // A database error on an admin URL (unreachable database, pending migrations…): a friendly
        // French 503 page instead of a 500 (docs/CMS.md §13 A3).
        $exceptions->render(fn (Throwable $e, Request $request) => AdminErrors::isAdmin($request) && ($error = AdminErrors::databaseError($e)) !== null
            ? AdminErrors::unavailable($request, $error)
            : null);
    })->create();
