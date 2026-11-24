<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\User;
use App\Services\Tuition;
use App\Support\Role;
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
        'students' => ['Students & QR IDs', "Almost done! Here's how students get in."],
        'done' => ['Done', "You're all set!"],
    ];

    public function show(string $step = 'welcome')
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        $keys = array_keys(self::STEPS);
        $i = array_search($step, $keys, true);
        $year = SchoolYear::current();

        return view('setup.wizard', [
            'step' => $step,
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
            'weights' => Subject::selectRaw('ww_weight, pt_weight, qa_weight, count(*) c')->groupBy('ww_weight', 'pt_weight', 'qa_weight')->orderByDesc('c')->first(),
            'tuition' => Tuition::current(),
        ]);
    }

    public function save(Request $request, string $step)
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        $message = $this->{'save'.Str::studly($step)}($request);
        $keys = array_keys(self::STEPS);
        $next = $keys[array_search($step, $keys, true) + 1] ?? 'done';
        if (session()->has('wizard_accounts')) {
            session()->reflash();

            return redirect()->route('setup.wizard', $step)->with('wizard_ok', $message); // show the passwords first
        }

        return redirect()->route('setup.wizard', $next)->with('wizard_ok', $message);
    }

    public function finish()
    {
        Setting::put('setup_completed_at', now()->toDateTimeString());

        return redirect()->route('dashboard')->with('ok', 'Setup finished. You can change everything later in School settings.');
    }

    private function saveSchool(Request $r): string
    {
        $d = $r->validate(['school_name' => 'required|max:150', 'school_address' => 'nullable|max:255', 'school_head' => 'nullable|max:150', 'school_phone' => 'nullable|max:50', 'school_email' => 'nullable|email|max:150']);
        foreach ($d as $k => $v) {
            Setting::put($k, (string) $v);
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

    private function saveLevels(Request $r): string
    {
        $d = $r->validate(['offered' => 'array', 'offered.*' => 'integer|exists:grade_levels,id']);
        $keep = collect($d['offered'] ?? [])->map(fn ($v) => (int) $v);
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
        // Re-link promotion order among the remaining levels.
        $prev = null;
        foreach (GradeLevel::orderBy('sort_order')->get() as $gl) {
            $prev?->update(['next_grade_level_id' => $gl->id]);
            $prev = $gl;
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
        $d = $r->validate(['ww' => 'required|integer|between:0,100', 'pt' => 'required|integer|between:0,100', 'qa' => 'required|integer|between:0,100']);
        if ($d['ww'] + $d['pt'] + $d['qa'] !== 100) {
            throw ValidationException::withMessages(['ww' => 'The three weights must add up to 100%.']);
        }
        Subject::query()->update(['ww_weight' => $d['ww'], 'pt_weight' => $d['pt'], 'qa_weight' => $d['qa']]);

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
        ]);

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
            Section::updateOrCreate(['name' => trim($s['name']), 'school_year_id' => $year->id],
                ['grade_level_id' => $s['grade_level_id'], 'adviser_id' => ($s['adviser_id'] ?? null) ?: null, 'room' => $room ?: null, 'capacity' => $cap]);
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
        session()->flash('wizard_accounts', $created);

        return count($created) ? 'Created '.count($created).' account(s). Write the temporary passwords down now – they are shown only once.' : 'No accounts added.';
    }
}
