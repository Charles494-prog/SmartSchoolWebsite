<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Support\Role;
use Illuminate\Support\Str;

/** Creates or updates the login account that belongs to an employee. */
class StaffAccounts
{
    /** @return string|null message to show (contains the one-time temporary password for new accounts) */
    public static function sync(Employee $e, ?string $email, ?string $role): ?string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null; // no login for this person (an existing login is left untouched)
        }
        abort_unless(array_key_exists((string) $role, Role::staffChoices(auth()->user())), 403, 'You cannot give that role.');
        $name = trim($e->first_name.' '.$e->last_name);

        if ($e->user) {
            abort_if($e->user->role === 'superadmin' && ! auth()->user()?->isAdmin(), 403);
            $e->user->update(['email' => $email, 'role' => $role, 'name' => $name]);

            return 'Login updated: '.$email.' · '.Role::label($role).'.';
        }

        $password = Str::password(10, symbols: false);
        $user = User::create(['name' => $name, 'email' => $email, 'role' => $role, 'password' => $password, 'must_change_password' => true]);
        $e->update(['user_id' => $user->id]);

        return "Login created for {$name}: {$email} / temporary password {$password} – give it to them; they must change it at first login. (Shown only once.)";
    }
}
