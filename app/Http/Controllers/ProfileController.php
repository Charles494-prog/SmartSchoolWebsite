<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/** "My profile": photo, name, short bio and password – for every signed-in user. */
class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['u' => $request->user()]);
    }

    public function update(Request $request)
    {
        $u = $request->user();
        $d = $request->validate([
            'name' => 'required|max:120',
            'bio' => 'nullable|max:500',
            'photo' => 'nullable|image|max:4096',
            'remove_photo' => 'boolean',
        ]);
        if ($request->hasFile('photo')) {
            $old = $u->avatar_path;
            $u->avatar_path = Photo::storeSquare($request->file('photo')->getRealPath(), 'avatars/'.$u->id.'-'.time().'.jpg');
            if ($old) {
                Storage::disk('local')->delete($old);
            }
        } elseif ($request->boolean('remove_photo') && $u->avatar_path) {
            Storage::disk('local')->delete($u->avatar_path);
            $u->avatar_path = null;
        }
        $u->fill(['name' => $d['name'], 'bio' => $d['bio'] ?? null])->save();

        return back()->with('ok', 'Profile saved.');
    }

    public function password(Request $request)
    {
        $u = $request->user();
        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $u->forceFill(['password' => Hash::make($request->password), 'must_change_password' => false])->save();

        return back()->with('ok', 'Password changed.');
    }

    /** Profile photos are private files: only signed-in users can see them. */
    public function photo(User $user)
    {
        abort_unless($user->avatar_path && Storage::disk('local')->exists($user->avatar_path), 404);

        return response()->file(Storage::disk('local')->path($user->avatar_path), ['Cache-Control' => 'private, max-age=600']);
    }
}
