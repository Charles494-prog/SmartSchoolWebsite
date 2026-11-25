<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Sends people to their personal first-time setup once (not the Superadmin – they have the school setup). */
class EnsureOnboarded
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && $u->role !== 'admin' && ! $u->onboarded_at && $request->isMethod('GET') && ! $request->expectsJson()
            && ! $request->routeIs('welcome', 'welcome.*', 'logout', 'password.*', 'profile.photo', 'dev.*', 'setup.*', 'first-run*')
            && ! app()->runningUnitTests()) {
            return redirect()->route('welcome');
        }

        return $next($request);
    }
}
