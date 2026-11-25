<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SignInCode;
use App\Support\TwoStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (FirstRunController::needed()) {
            return redirect()->route('first-run');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required']);
        $key = Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Wrong email or password.']);
        }
        RateLimiter::clear($key);
        $user = $request->user();
        if (TwoStep::requiredFor($user)) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('two_step', ['id' => $user->id, 'remember' => $request->boolean('remember')]);
            if (! $this->sendCode($user)) {
                $request->session()->forget('two_step');
                throw ValidationException::withMessages(['email' => 'We could not send your sign-in code by email. Please tell the school office.']);
            }

            return redirect()->route('login.verify');
        }

        return $this->finishLogin($request, $user);
    }

    private function finishLogin(Request $request, User $user)
    {
        $request->session()->regenerate();
        $user->updateQuietly(['last_login_at' => now()]);
        AuditLog::record('login', $user, description: 'Logged in');

        return redirect()->intended($user->hasRole('student', 'parent') ? route('portal') : ($user->role === 'guard' ? route('attendance.scan') : route('dashboard')));
    }

    /** Emails a new 6-digit code (valid 10 minutes, 5 tries). Returns false if the email could not be sent. */
    private function sendCode(User $user): bool
    {
        $code = (string) random_int(100000, 999999);
        Cache::put('two_step:'.$user->id, ['hash' => Hash::make($code), 'tries' => 0], now()->addMinutes(10));
        try {
            $user->notify(new SignInCode($code, (string) Setting::get('school_name')));
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    public function showVerify(Request $request)
    {
        $pending = $request->session()->get('two_step');
        if (! $pending || ! ($user = User::find($pending['id']))) {
            return redirect()->route('login');
        }

        return view('auth.verify', ['email' => preg_replace('/(?<=.).(?=[^@]*@)/', '•', $user->email)]);
    }

    public function verify(Request $request)
    {
        $pending = $request->session()->get('two_step');
        $user = $pending ? User::find($pending['id']) : null;
        if (! $user) {
            return redirect()->route('login');
        }
        $data = $request->validate(['code' => 'required|digits:6']);
        $entry = Cache::get('two_step:'.$user->id);
        if (! $entry || $entry['tries'] >= 5) {
            Cache::forget('two_step:'.$user->id);
            throw ValidationException::withMessages(['code' => 'This code has expired or had too many tries. Send a new code.']);
        }
        if (! Hash::check($data['code'], $entry['hash'])) {
            Cache::put('two_step:'.$user->id, ['hash' => $entry['hash'], 'tries' => $entry['tries'] + 1], now()->addMinutes(10));
            throw ValidationException::withMessages(['code' => 'That code is not right. Check the latest email and try again.']);
        }
        Cache::forget('two_step:'.$user->id);
        $request->session()->forget('two_step');
        Auth::login($user, (bool) $pending['remember']);

        return $this->finishLogin($request, $user);
    }

    public function resendCode(Request $request)
    {
        $pending = $request->session()->get('two_step');
        $user = $pending ? User::find($pending['id']) : null;
        if (! $user) {
            return redirect()->route('login');
        }
        $key = 'two_step_resend:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return back()->withErrors(['code' => 'Please wait '.RateLimiter::availableIn($key).' seconds before asking for another code.']);
        }
        RateLimiter::hit($key, 60);

        $sent = $this->sendCode($user);

        return back()->with($sent ? 'ok' : 'error', $sent ? 'A new code was sent to your email.' : 'The email could not be sent. Please tell the school office.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $u = $request->user();
        $u->update(['password' => Hash::make($request->password), 'must_change_password' => false]);

        return redirect()->route($u->hasRole('student', 'parent') ? 'portal' : 'dashboard')->with('ok', 'Password changed.');
    }
}
