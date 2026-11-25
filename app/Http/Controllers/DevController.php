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
        // Demo accounts, plus the Superadmin/admin accounts so their screens can be checked too.
        return User::where('is_active', true)->where(fn ($q) => $q->where('role', 'admin')->orWhere(fn ($d) => $d->where('email', 'like', '%@school.test')
            ->where(fn ($w) => $w->where('role', '!=', 'parent')->orWhere('email', 'parent@school.test'))))
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
        $user = User::whereKey($request->validate(['user_id' => 'required|integer'])['user_id'])->where(fn ($q) => $q->where('email', 'like', '%@school.test')->orWhere('role', 'admin'))->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('ok', 'Dev mode: signed in as '.$user->name.' ('.$user->role.').');
    }

    /** Run the setup wizard again from the start (as the Superadmin) to check every step. */
    public function setup(Request $request)
    {
        abort_unless(self::enabled(), 404);
        if ($request->user()->role !== 'admin') {
            Auth::login(User::where('role', 'admin')->where('is_active', true)->orderBy('id')->firstOrFail());
            $request->session()->regenerate();
        }
        Setting::put('setup_completed_at', '');

        return redirect()->route('setup.wizard', 'welcome')->with('ok', 'Dev mode: setup wizard restarted. Your data stays; each step shows what is already set.');
    }
}
