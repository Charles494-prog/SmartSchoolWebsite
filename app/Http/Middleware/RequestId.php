<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Gives every request an ID, adds it to every log line and returns it in X-Request-Id. */
class RequestId
{
    public function handle(Request $request, Closure $next)
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id, 'user_id' => $request->user()?->id, 'path' => $request->path()]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
