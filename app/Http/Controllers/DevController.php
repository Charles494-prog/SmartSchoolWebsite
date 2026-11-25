<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dev mode (SMARTSCHOOL_DEV=true in .env): switch LAN/Online instantly and sign in as a demo account,
 * to test every feature on one computer. Off by default; never enable it on a real school's server.
 */
class DevController extends Controller
{
    public static function enabled(): bool
    {
        return (bool) config('smartschool.dev');
    }

    /** Demo accounts only (the sample data uses @school.test addresses). */
    public static function accounts()
    {
        return User::where('email', 'like', '%@school.test')->where('is_active', true)
            ->where(fn ($q) => $q->where('role', '!=', 'parent')->orWhere('email', 'parent@school.test'))
            ->orderBy('role')->orderBy('email')->get(['id', 'name', 'email', 'role']);
    }

    public function mode(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $mode = $request->validate(['mode' => 'required|in:lan,hosted'])['mode'];
        Setting::put('deployment_mode', $mode);

        return back()->with('ok', 'Dev mode: now running as '.($mode === School::LAN ? 'Offline (LAN)' : 'Online').'.');
    }

    public function login(Request $request)
    {
        abort_unless(self::enabled(), 404);
        $user = User::whereKey($request->validate(['user_id' => 'required|integer'])['user_id'])->where('email', 'like', '%@school.test')->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('ok', 'Dev mode: signed in as '.$user->name.' ('.$user->role.').');
    }
}
