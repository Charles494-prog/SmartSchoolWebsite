<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Blocks deactivated accounts and forces a password change when required. */
class AccountGuard
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && ! $u->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'This account is deactivated. Please contact the school.']);
        }
        if ($u && $u->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit')->with('error', 'Please set a new password before continuing.');
        }

        return $next($request);
    }
}
