<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Award;
use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Tuition;
use App\Support\Admission;
use App\Support\GradeScale;
use App\Support\Role;
use App\Support\School;
use App\Support\SchoolMail;
use App\Support\TwoStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Step-by-step setup shown as a pop-up after the Superadmin is created.
 * Every step can be skipped; finishing (or closing) marks setup as seen.
 */
class SetupWizardController extends Controller
{
    /** key => [title shown in the progress list, question asked by the assistant] */
    public const STEPS = [
        'welcome' => ['Get started', "Let's get started!"],
        'school' => ['Your school', 'First, tell me about your school.'],
        'year' => ['School year', 'Which school year are we setting up?'],
        'levels' => ['Grade levels', 'Which grade levels does your school offer?'],
        'rooms' => ['Rooms', 'What rooms do you have, and how many students fit in each?'],
        'subjects' => ['Subjects & grading', 'How are grades computed?'],
        'staff' => ['Staff accounts', 'Who will use SmartSchool with you?'],
        'sections' => ['Sections', "Let's create your sections."],
        'fees' => ['Fees', 'Is your school public or private?'],
        'documents' => ['Admission documents', 'Which documents should families submit?'],
        'email' => ['Email & sign-in', 'Which email account should SmartSchool send from?'],
        'students' => ['Students & QR IDs', "Almost done! Here's how students get in."],
        'done' => ['Done', "You're all set!"],
    ];

    /** Steps for this school: email/sign-in security only matter when SmartSchool runs Online. */
    public static function steps(): array
    {
        $steps = self::STEPS;
        if (! School::allows('email_notifications')) {
            unset($steps['email']);
        }

        return $steps;
    }

    public function show(string $step = 'welcome')
    {
        abort_unless(isset(self::steps()[$step]), 404);
        $accounts = $step === 'staff' ? session('wizard_accounts') : null;
        session()->forget('wizard_accounts');
        $keys = array_keys(self::steps());
        $i = array_search($step, $keys, true);
        $year = SchoolYear::current();

        return view('setup.wizard', [
            'step' => $step,
            'accounts' => $accounts,
            'index' => $i,
            'total' => count($keys) - 2, // welcome and done are not counted
            'next' => $keys[$i + 1] ?? null,
            'prev' => $keys[$i - 1] ?? null,
            'year' => $year,
            'levels' => GradeLevel::orderBy('sort_order')->get(),
            'rooms' => Room::orderBy('name')->get(),
            'teachers' => User::where('role', Role::TEACHER)->orderBy('name')->pluck('name', 'id'),
            'staff' => User::whereNotIn('role', [Role::STUDENT, Role::PARENT])->where('id', '!=', auth()->id())->orderBy('name')->get(),
            'sections' => $year ? Section::with('gradeLevel', 'adviser')->where('school_year_id', $year->id)->get() : collect(),
            'weights' => Subject::selectRaw('ww_weight, pt_weight, qa_weight, at_weight, count(*) c')->groupBy('ww_weight', 'pt_weight', 'qa_weight', 'at_weight')->orderByDesc('c')->first(),
            'tuition' => Tuition::current(),
        ]);
    }

    public function save(Request $request, string $step)
    {
        abort_unless(isset(self::steps()[$step]), 404);
        $message = $this->{'save'.Str::studly($step)}($request);
        $keys = array_keys(self::steps());
        $next = $keys[array_search($step, $keys, true) + 1] ?? 'done';
        if (session('wizard_accounts')) {
            return redirect()->route('setup.wizard', $step)->with('wizard_ok', $message); // show the passwords first
        }

        return redirect()->route('setup.wizard', $next)->with('wizard_ok', $message);
    }

    public function finish()
    {
        Setting::put('setup_completed_at', now()->toDateTimeString());

        return redirect()->route('dashboard')->with('ok', 'Setup finished. You can change everything later in School settings.');
    }

    private function saveEmail(Request $r): string
    {
        $d = $r->validate(SchoolMail::rules() + ['two_step' => 'nullable|in:'.implode(',', array_keys(TwoStep::OPTIONS))]);
        SchoolMail::save($d);
        $two = $d['two_step'] ?? 'off';
        if ($two !== 'off' && ! TwoStep::available()) {
            throw ValidationException::withMessages(['two_step' => 'Two-step sign-in needs working email. Fill in the email settings first.']);
        }
        Setting::put('two_step', $two);

        return SchoolMail::configured() ? 'Email settings saved. Send a test from School settings any time.' : 'Saved. You can set up email later in School settings.';
    }

    private function saveDocuments(Request $r): string
    {
        $r->validate(Admission::rules());
        Admission::save($r->input('docs', []));

        return 'Admission documents saved.';
    }

    private function saveSchool(Request $r): string
    {
        $d = $r->validate(['school_name' => 'required|max:150', 'school_address' => 'nullable|max:255', 'school_head' => 'nullable|max:150', 'school_phone' => 'nullable|max:50', 'school_email' => 'nullable|email|max:150', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048']);
        foreach (collect($d)->except('logo') as $k => $v) {
            Setting::put($k, (string) $v);
        }
        if ($r->hasFile('logo')) {
            Setting::storeLogo($r->file('logo'));
        }

        return 'Saved your school details.';
    }

    private function saveYear(Request $r): string
    {
        $d = $r->validate([
            'name' => 'required|max:20',
            'enrollment_opens_on' => 'nullable|date|before_or_equal:starts_on',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
        ]);
        DB::transaction(function () use ($d) {
            SchoolYear::query()->update(['is_current' => false]);
            SchoolYear::updateOrCreate(['name' => $d['name']], $d + ['is_current' => true]);
        });

        return "School year {$d['name']} is now the current school year.";
    }

    private const ORDINALS = ['1st', '2nd', '3rd', '4th', '5th', '6th'];

    /** "BSIT – 2nd Year" → "BSIT" */
    private static function program(string $name): string
    {
        return trim(explode(' – ', $name)[0]);
    }

    private function saveLevels(Request $r): string
    {
        $d = $r->validate([
            'offered' => 'array', 'offered.*' => 'integer|exists:grade_levels,id',
            'names' => 'array', 'names.*' => 'nullable|string|max:60',
            'new_levels' => 'array|max:30', 'new_levels.*.name' => 'nullable|string|max:60', 'new_levels.*.department' => 'nullable|in:'.implode(',', array_keys(GradeLevel::DEPARTMENTS)),
            'programs' => 'array|max:30', 'programs.*.name' => 'nullable|string|max:45', 'programs.*.code' => 'nullable|string|max:12', 'programs.*.years' => 'nullable|integer|between:1,6',
        ]);
        $keep = collect($d['offered'] ?? [])->map(fn ($v) => (int) $v);
        foreach ($d['names'] ?? [] as $id => $name) {
            $name = trim((string) $name);
            if ($name !== '' && $keep->contains((int) $id)) {
                GradeLevel::whereKey((int) $id)->where('name', '!=', $name)->first()?->update(['name' => $name]);
            }
        }
        // Hide levels the school doesn't offer by removing only those that are not used anywhere yet.
        $removed = 0;
        foreach (GradeLevel::whereNotIn('id', $keep)->get() as $gl) {
            $used = Section::where('grade_level_id', $gl->id)->exists() || DB::table('enrollments')->where('grade_level_id', $gl->id)->exists();
            if (! $used) {
                Subject::where('grade_level_id', $gl->id)->delete();
                DB::table('fee_items')->where('grade_level_id', $gl->id)->delete();
                GradeLevel::whereKey($gl->id)->update(['next_grade_level_id' => null]);
                GradeLevel::where('next_grade_level_id', $gl->id)->update(['next_grade_level_id' => null]);
                $gl->delete();
                $removed++;
            }
        }
        $order = (int) GradeLevel::max('sort_order');
        $terms = School::periods();
        $added = 0;
        $make = function (string $name, string $dept) use (&$order, &$added, $terms) {
            if ($name === '' || GradeLevel::where('name', $name)->exists()) {
                return;
            }
            GradeLevel::create(['name' => $name, 'department' => $dept, 'sort_order' => ++$order, 'terms' => $terms, 'numeric_grades' => $dept !== 'kinder']);
            $added++;
        };
        foreach ($d['new_levels'] ?? [] as $l) {
            $make(trim((string) ($l['name'] ?? '')), $l['department'] ?? 'other');
        }
        foreach ($d['programs'] ?? [] as $p) {
            $label = trim((string) ($p['code'] ?? '')) ?: trim((string) ($p['name'] ?? ''));
            for ($y = 1; $label !== '' && $y <= (int) ($p['years'] ?? 4); $y++) {
                $make($label.' – '.self::ORDINALS[$y - 1].' Year', 'college');
            }
        }
        // Re-link promotion order: basic ed in sequence; each college program only within itself.
        $prev = null;
        foreach (GradeLevel::orderBy('sort_order')->get() as $gl) {
            $link = $prev && ($gl->department !== 'college' || ($prev->department === 'college' && self::program($prev->name) === self::program($gl->name)));
            $prev?->update(['next_grade_level_id' => $link ? $gl->id : null]);
            $prev = $gl;
        }
        $prev?->update(['next_grade_level_id' => null]);
        if ($added) {
            return "Added {$added} level(s)".($removed ? ", removed {$removed}" : '').'.';
        }

        return $removed ? "Removed {$removed} grade level(s) your school doesn't offer." : 'Grade levels saved.';
    }

    private function saveRooms(Request $r): string
    {
        $d = $r->validate(['rooms' => 'array', 'rooms.*.name' => 'nullable|max:40', 'rooms.*.capacity' => 'nullable|integer|min:1|max:500']);
        $n = 0;
        foreach ($d['rooms'] ?? [] as $row) {
            if (trim((string) ($row['name'] ?? '')) === '') {
                continue;
            }
            Room::updateOrCreate(['name' => trim($row['name'])], ['capacity' => (int) ($row['capacity'] ?? 40) ?: 40]);
            $n++;
        }

        return "Saved {$n} room(s).";
    }

    private function saveSubjects(Request $r): string
    {
        $d = $r->validate(['ww' => 'required|integer|between:0,100', 'pt' => 'required|integer|between:0,100', 'qa' => 'required|integer|between:0,100', 'at' => 'nullable|integer|between:0,100',
            'grading_periods' => 'nullable|in:2,3,4', 'honors_top_n' => 'nullable|integer|between:3,100',
            'honors_titles_when' => 'nullable|in:each_period,year_end', 'honors_titles_levels' => 'nullable|array', 'honors_titles_levels.*' => 'integer']);
        $r->validate(['passing_grade' => 'nullable|numeric|between:50,100']);
        GradeScale::saveFrom($r);
        if ($r->filled('passing_grade')) {
            Setting::put('passing_grade', (string) $r->input('passing_grade'));
        }
        $d['at'] = (int) ($d['at'] ?? 0);
        if ($d['ww'] + $d['pt'] + $d['qa'] + $d['at'] !== 100) {
            throw ValidationException::withMessages(['ww' => 'The weights must add up to 100%.']);
        }
        Subject::query()->update(['ww_weight' => $d['ww'], 'pt_weight' => $d['pt'], 'qa_weight' => $d['qa'], 'at_weight' => $d['at']]);
        if (! empty($d['grading_periods']) && ! Assessment::where('term', '>', (int) $d['grading_periods'])->exists()) {
            GradeLevel::query()->update(['terms' => (int) $d['grading_periods']]);
        }
        Setting::put('honors_titles_when', $d['honors_titles_when'] ?? 'each_period');
        Setting::put('honors_titles_levels', implode(',', $d['honors_titles_levels'] ?? []));
        if (! empty($d['honors_top_n'])) {
            Setting::put('honors_top_n', (string) $d['honors_top_n']);
        }
        if (! Award::exists()) {
            foreach (Award::DEFAULTS as [$name, $desc]) {
                Award::create(['name' => $name, 'description' => $desc]);
            }
        }

        return 'Grading weights applied to every subject.';
    }

    private function saveStaff(Request $r): string
    {
        $roles = array_keys(Role::staffChoices($r->user()));
        $d = $r->validate([
            'people' => 'array',
            'people.*.name' => 'nullable|max:120',
            'people.*.email' => 'nullable|required_with:people.*.name|email|max:150|distinct|unique:users,email',
            'people.*.role' => ['nullable', 'required_with:people.*.name', Rule::in($roles)],
        ], ['people.*.email.unique' => 'This email already has an account – see "Already added" and use "New temporary password" if you lost it.']);

        return $this->createAccounts(collect($d['people'] ?? [])->filter(fn ($p) => trim((string) ($p['name'] ?? '')) !== ''));
    }

    private function saveSections(Request $r): string
    {
        $year = SchoolYear::current();
        abort_unless($year, 422, 'Set up the school year first.');
        $d = $r->validate([
            'sections' => 'array',
            'sections.*.name' => 'nullable|max:60',
            'sections.*.grade_level_id' => 'nullable|required_with:sections.*.name|exists:grade_levels,id',
            'sections.*.adviser_id' => 'nullable|exists:users,id',
            'sections.*.room' => 'nullable|max:40',
        ]);
        $n = 0;
        foreach ($d['sections'] ?? [] as $s) {
            if (trim((string) ($s['name'] ?? '')) === '') {
                continue;
            }
            $room = $s['room'] ?? null;
            $cap = $room ? (Room::where('name', $room)->value('capacity') ?? 40) : 40;
            Section::updateOrCreate(['name' => trim($s['name']), 'school_year_id' => $year->id, 'grade_level_id' => $s['grade_level_id']],
                ['adviser_id' => ($s['adviser_id'] ?? null) ?: null, 'room' => $room ?: null, 'capacity' => $cap]);
            $n++;
        }

        return "Saved {$n} section(s). Assign subject teachers in Classes & sections.";
    }

    private function saveFees(Request $r): string
    {
        $d = $r->validate([
            'school_type' => 'required|in:public,private',
            'tuition_mode' => 'required_if:school_type,private|in:same,per_level',
            'tuition_all' => 'nullable|numeric|min:0|max:10000000',
            'tuition' => 'array', 'tuition.*' => 'nullable|numeric|min:0|max:10000000',
        ]);
        Tuition::save($d['school_type'], $d['tuition_mode'] ?? 'per_level', isset($d['tuition_all']) ? (float) $d['tuition_all'] : null, $d['tuition'] ?? []);

        return $d['school_type'] === 'public' ? 'Public school: no tuition.' : 'Tuition saved.';
    }

    /** Creates logins with one-time temporary passwords; the passwords are shown once on the next screen. */
    private function createAccounts($people, bool $mustChange = true): string
    {
        $created = [];
        foreach ($people as $p) {
            $password = Str::password(10, symbols: false);
            User::create(['name' => trim($p['name']), 'email' => $p['email'], 'role' => $p['role'], 'password' => $password, 'must_change_password' => $mustChange]);
            $created[] = ['name' => trim($p['name']), 'email' => $p['email'], 'role' => Role::label($p['role']), 'password' => $password];
        }
        if ($created) {
            session()->put('wizard_accounts', $created); // shown until the admin continues
        }

        return $created ? 'Created '.count($created).' account(s). Write the temporary passwords down now.' : 'No new accounts – moving on.';
    }

    /** New temporary password for someone added during setup (e.g. the password was not written down). */
    public function resetPassword(User $user)
    {
        abort_if($user->isAdmin() || in_array($user->role, [Role::STUDENT, Role::PARENT], true), 403);
        $password = Str::password(10, symbols: false);
        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();
        session()->put('wizard_accounts', [['name' => $user->name, 'email' => $user->email, 'role' => Role::label($user->role), 'password' => $password]]);

        return redirect()->route('setup.wizard', 'staff')->with('wizard_ok', 'New temporary password made for '.$user->name.'.');
    }
}
