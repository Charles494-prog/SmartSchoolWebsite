<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            ->get(['id', 'name', 'email', 'role'])
            ->sortBy(fn ($u) => [array_search($u->role, ['admin', 'student', 'parent', 'teacher', 'registrar', 'cashier', 'guard', 'nurse', 'guidance', 'librarian', 'hr'], true), $u->name])->values();
    }

    /** The real (Superadmin) account behind a dev-mode session, even while viewing as someone else. */
    public static function realUser(): ?User
    {
        $id = session('dev_real_user_id');

        return $id ? User::where('id', $id)->where('role', 'admin')->where('is_active', true)->first() : null;
    }

    /** Dev tools show only to a Superadmin – or to someone a Superadmin is currently "viewing as". */
    public static function visible(): bool
    {
        return self::enabled() && auth()->check() && (auth()->user()->role === 'admin' || self::realUser() !== null);
    }

    public static function impersonating(): bool
    {
        return self::realUser() !== null && self::realUser()->id !== auth()->id();
    }

    /** Developer tools are hidden behind a password (its hash lives in .env as SMARTSCHOOL_DEV_PASSWORD_HASH). */
    public function unlock(Request $request)
    {
        abort_unless(self::visible(), 404);
        $hash = (string) config('smartschool.dev_password_hash');
        $ok = $hash !== '' && Hash::check((string) $request->input('password'), $hash);
        if (! $ok) {
            return back()->with('error', 'Wrong developer password.');
        }
        $request->session()->put('dev_unlocked', true);
        $request->session()->put('dev_real_user_id', self::realUser()?->id ?? $request->user()->id);

        return back()->with('ok', 'Developer mode unlocked.');
    }

    private function unlocked(): void
    {
        // Authorised by the REAL signed-in Superadmin, never by the account being viewed.
        abort_unless(self::enabled() && session('dev_unlocked') && self::realUser(), 404);
    }

    public function mode(Request $request)
    {
        $this->unlocked();
        $mode = $request->validate(['mode' => 'required|in:lan,hosted'])['mode'];
        Setting::put('deployment_mode', $mode);

        return back()->with('ok', 'Dev mode: now running as '.($mode === School::LAN ? 'Offline (LAN)' : 'Online').'.');
    }

    public function login(Request $request)
    {
        $this->unlocked();
        $user = User::whereKey($request->validate(['user_id' => 'required|integer'])['user_id'])->where(fn ($q) => $q->where('email', 'like', '%@school.test')->orWhere('role', 'admin'))->firstOrFail();
        $this->switchTo($request, $user);

        return redirect('/')->with('ok', 'Dev mode: viewing as '.$user->name.' ('.$user->roleLabel().').');
    }

    /** Back to the real Superadmin account. */
    public function back(Request $request)
    {
        $real = self::realUser();
        abort_unless(self::enabled() && $real, 404);
        $this->switchTo($request, $real);

        return redirect('/')->with('ok', 'Back to your Superadmin account.');
    }

    private function switchTo(Request $request, User $user): void
    {
        $real = self::realUser();
        $from = $request->user();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('dev_unlocked', true);
        $request->session()->put('dev_real_user_id', $real->id);
        AuditLog::create(['user_id' => $real->id, 'action' => $user->id === $real->id ? 'impersonate_end' : 'impersonate', 'auditable_type' => 'User', 'auditable_id' => $user->id,
            'description' => $user->id === $real->id ? "Dev mode: {$real->name} returned from viewing as {$from->name}" : "Dev mode: {$real->name} is viewing as {$user->name} ({$user->role})",
            'old_values' => ['user_id' => $from->id], 'new_values' => ['user_id' => $user->id], 'ip' => $request->ip(), 'created_at' => now()]);
    }

    /** Run the setup wizard again from the start (as the Superadmin) to check every step. */
    public function setup(Request $request)
    {
        $this->unlocked();
        if ($request->user()->role !== 'admin') {
            $this->switchTo($request, self::realUser());
        }
        Setting::put('setup_completed_at', '');

        return redirect()->route('setup.wizard', 'welcome')->with('ok', 'Dev mode: setup wizard restarted. Your data stays; each step shows what is already set.');
    }
}
