<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/** First launch: no administrator exists yet, so the person installing creates the Superadmin. */
class FirstRunController extends Controller
{
    public static function needed(): bool
    {
        return ! User::where('role', Role::ADMIN)->exists();
    }

    public function show()
    {
        abort_unless(self::needed(), 404);

        return view('auth.first-run');
    }

    public function store(Request $request)
    {
        abort_unless(self::needed(), 404);
        $data = $request->validate([
            'school_name' => 'required|max:150',
            'name' => 'required|max:120',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'written_down' => 'accepted',
        ], ['written_down.accepted' => 'Please confirm you wrote the email and password down.']);

        Setting::put('school_name', $data['school_name']);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => Role::ADMIN, 'must_change_password' => false]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('setup.wizard');
    }
}
