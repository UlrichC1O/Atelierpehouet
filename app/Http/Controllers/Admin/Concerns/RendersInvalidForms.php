<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Contracts\Support\MessageProvider;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Shows an invalid admin form again — HTTP 422, errors + submitted values — instead of redirecting
 * back with flashed input (docs/CMS.md §7.0).
 *
 * On Vercel the session is an encrypted 4 KB cookie: flashing a long text form would overflow it.
 * The submitted values are put in the session with now(), which Laravel drops before saving, so
 * old() works in the re-rendered view without the input ever reaching the cookie.
 */
trait RendersInvalidForms
{
    /** Fields never sent back to the browser. */
    private const NOT_REPLAYED = ['_token', '_method', 'password', 'password_confirmation', 'current_password'];

    /**
     * @param  array<string, mixed>  $data  view data (the view also receives $errors)
     * @param  Validator|MessageProvider|MessageBag|array<string, string|list<string>>  $errors
     */
    protected function invalid(Request $request, string $view, array $data, Validator|MessageProvider|MessageBag|array $errors): Response
    {
        $bag = match (true) {
            $errors instanceof Validator => $errors->errors(),
            $errors instanceof MessageBag => $errors,
            $errors instanceof MessageProvider => $errors->getMessageBag(),
            default => new MessageBag($errors),
        };

        if ($request->hasSession()) {
            $request->session()->now('_old_input', $request->except(self::NOT_REPLAYED));
        }

        // ShareErrorsFromSession already shared an empty bag: anonymous components (<x-admin.field>)
        // only see shared data, so the new bag is shared too, not just passed to the view.
        $errorBag = (new ViewErrorBag)->put('default', $bag);
        View::share('errors', $errorBag);

        return response()->view($view, $data + ['errors' => $errorBag], 422);
    }
}
