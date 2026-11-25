<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Setting;
use App\Models\User;
use App\Support\Nav;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Personal first-time setup for everyone except the Superadmin (who has the school setup).
 * Each role gets its own questions; the menu can be trimmed, but never below what the admin made required.
 */
class WelcomeController extends Controller
{
    public static function steps(User $u): array
    {
        $steps = ['hello' => 'Welcome', 'look' => 'Look & start page', 'menu' => 'Your menu'];
        if ($u->role === Role::STUDENT) {
            $steps += ['classes' => 'My classes', 'parents' => 'Parents'];
            if (Setting::get('school_type') === 'private') {
                $steps['payments'] = 'Payments';
            }
        }
        if ($u->role === Role::TEACHER) {
            $steps['teaching'] = 'Teaching';
        }
        if ($u->role === Role::PARENT) {
            $steps['family'] = 'Updates';
        }

        return $steps + ['done' => 'Done'];
    }

    public function show(Request $request)
    {
        $u = $request->user();
        abort_if($u->role === Role::ADMIN, 404);
        $features = collect(Nav::full($u))->flatten(1)->unique('label')->values();
        $locked = Nav::locked($u->role);
        $student = $u->role === Role::STUDENT ? $u->student : null;
        $parents = $student ? $student->guardians()->with('user')->get() : collect();

        return view('welcome.wizard', [
            'u' => $u, 'steps' => self::steps($u), 'features' => $features, 'locked' => $locked, 'hidden' => (array) $u->pref('hidden', []),
            'starts' => $features->filter(fn ($f) => ! in_array($f['label'], ['How it works'], true))->values(),
            'online' => School::allows('classwork'), 'parents' => $parents, 'student' => $student,
        ]);
    }

    public function save(Request $request)
    {
        $u = $request->user();
        abort_if($u->role === Role::ADMIN, 404);
        $labels = collect(Nav::full($u))->flatten(1)->pluck('label')->all();
        $d = $request->validate([
            'theme' => 'nullable|in:light,dark,system', 'start' => 'nullable|string|max:80',
            'hidden' => 'array', 'hidden.*' => 'string',
            'hide_turned_in' => 'boolean', 'todo_home' => 'boolean', 'live_reports' => 'boolean', 'payer' => 'nullable|in:parent,student,in_person',
            'notify_submissions' => 'boolean', 'notify_grades' => 'boolean', 'notify_attendance' => 'boolean',
            'parent_name' => 'nullable|max:120', 'parent_email' => 'nullable|email|max:150', 'parent_relationship' => 'nullable|max:40',
        ]);
        $hidden = array_values(array_diff(array_intersect($d['hidden'] ?? [], $labels), Nav::locked($u->role)));
        $prefs = array_merge($u->preferences ?? [], [
            'theme' => $d['theme'] ?? 'system', 'start' => in_array($d['start'] ?? '', $labels, true) ? $d['start'] : 'Home', 'hidden' => $hidden,
            'hide_turned_in' => $request->boolean('hide_turned_in'), 'todo_home' => $request->boolean('todo_home', true), 'live_reports' => $request->boolean('live_reports'),
            'payer' => $d['payer'] ?? null, 'notify_submissions' => $request->boolean('notify_submissions', true),
            'notify_grades' => $request->boolean('notify_grades', true), 'notify_attendance' => $request->boolean('notify_attendance', true),
        ]);
        $u->update(['preferences' => $prefs, 'onboarded_at' => now()]);

        // A student invites a parent: the account is made here and linked to the student.
        $msg = 'All set! You can change any of this later in My profile → Personal setup.';
        if ($u->role === Role::STUDENT && ! empty($d['parent_email']) && ($student = $u->student) && School::allows('parent_accounts')) {
            $existing = User::where('email', $d['parent_email'])->first();
            abort_if($existing && $existing->role !== Role::PARENT, 422, 'That email belongs to a staff or student account.');
            $temp = Str::password(10, symbols: false);
            $parent = $existing ?? User::create(['name' => $d['parent_name'] ?: 'Parent of '.$student->first_name, 'email' => $d['parent_email'], 'password' => $temp, 'role' => Role::PARENT, 'must_change_password' => true]);
            $g = Guardian::firstOrCreate(['user_id' => $parent->id], ['name' => $parent->name, 'email' => $parent->email]);
            $student->guardians()->syncWithoutDetaching([$g->id => ['relationship' => $d['parent_relationship'] ?: 'Parent', 'is_primary' => $student->guardians()->doesntExist()]]);
            $msg = $existing ? $parent->name.' is now linked to your account.' : 'Parent account made for '.$parent->email.' – temporary password: '.$temp.' (they change it on first sign-in).';
        }

        return redirect($this->startUrl($u->fresh()))->with('ok', $msg);
    }

    /** Where this person wants to land. */
    public function startUrl(User $u): string
    {
        $item = collect(Nav::for($u))->flatten(1)->firstWhere('label', $u->pref('start', 'Home'));

        return $item['url'] ?? route('dashboard');
    }
}
