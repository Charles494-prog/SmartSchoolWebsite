<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Uptime check for monitors and the LAN launcher. Reveals no internals. */
class HealthController extends Controller
{
    public function show()
    {
        $checks = ['database' => false, 'storage' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (Throwable) {
        }
        try {
            $checks['storage'] = Storage::disk('local')->put('.health', (string) time());
        } catch (Throwable) {
        }
        $ok = ! in_array(false, $checks, true);

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks, 'time' => now()->toIso8601String()], $ok ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
