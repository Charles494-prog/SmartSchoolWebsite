<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/** Rate-limits only write requests; page views are not throttled here. */
class ThrottleWrites
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'writes');
    }
}
