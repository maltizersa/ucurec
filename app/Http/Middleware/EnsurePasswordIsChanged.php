<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After an administrator resets a password, the user must replace the
 * temporary password before using the rest of the system.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs('account.password.*', 'logout')) {
            return redirect()->route('account.password.edit')
                ->with('warning', 'Please replace your temporary password before continuing.');
        }

        return $next($request);
    }
}
