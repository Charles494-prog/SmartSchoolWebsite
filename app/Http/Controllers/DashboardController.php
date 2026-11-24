<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\FeeItem;
use App\Models\GradeLevel;
use App\Models\Payment;
use App\Models\Room;
use App\Models\ScheduleSlot;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\StudentAccount;
use App\Models\Subject;
use App\Models\User;
use App\Services\Grading;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        if ($u->hasRole(Role::STUDENT, Role::PARENT)) {
            return redirect()->route('portal');
        }
        // Pop up the setup wizard once per sign-in until the school is set up.
        if ($u->isAdmin() && ! School::isSetUp() && ! Section::exists() && ! $request->session()->has('wizard_seen')) {
            $request->session()->put('wizard_seen', true);

            return redirect()->route('setup.wizard');
        }
        if ($u->role === Role::GUARD) {
            return redirect()->route('attendance.scan');
        }
        $sy = SchoolYear::current();
        $data = ['sy' => $sy, 'u' => $u];

        // Getting-started checklist for administrators
        if ($u->isAdmin()) {
            $data['steps'] = [
                ['School information & logo', Setting::get('school_name') !== 'My School', route('settings.edit')],
                ['Current school year', (bool) $sy, route('crud.index', 'school-years')],
                ['Grade levels', GradeLevel::exists(), route('crud.index', 'grade-levels')],
                ['Rooms and their seats', Room::exists(), route('crud.index', 'rooms')],
                ['Subjects with grading weights', Subject::exists(), route('crud.index', 'subjects')],
                ['Staff accounts (registrar, teachers, cashier…)', User::where('role', Role::TEACHER)->exists(), route('crud.index', 'users')],
                ['Sections, advisers & teachers per subject', Section::where('school_year_id', $sy?->id)->exists(), route('sections.index')],
                ['Fees for the school year', FeeItem::where('school_year_id', $sy?->id)->exists(), route('crud.index', 'fee-items')],
                ['Enroll students (or share the online application link)', Enrollment::where('school_year_id', $sy?->id)->exists(), route('applications.index')],
                ['Print QR ID cards for students', Enrollment::where('school_year_id', $sy?->id)->whereNotNull('section_id')->exists() && Setting::get('qr_printed') === '1', route('qr.cards')],
            ];
        }

        if ($u->hasRole(Role::ADMIN, Role::REGISTRAR, Role::CASHIER)) {
            $enrolled = Enrollment::where('school_year_id', $sy?->id)->where('status', 'enrolled');
            $accounts = StudentAccount::whereHas('enrollment', fn ($e) => $e->where('school_year_id', $sy?->id))->get();
            $billed = $accounts->sum('net_amount');
            $collected = (float) Payment::where('is_void', false)->whereIn('student_account_id', $accounts->pluck('id'))->sum('amount');
            $today = AttendanceRecord::whereDate('date', today())->whereNull('class_subject_id')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
            $data += [
                'enrolledCount' => (clone $enrolled)->count(),
                'byLevel' => GradeLevel::orderBy('sort_order')->get()->map(fn ($g) => [$g->name, Enrollment::where('school_year_id', $sy?->id)->where('status', 'enrolled')->where('grade_level_id', $g->id)->count()])->filter(fn ($r) => $r[1] > 0)->values(),
                'trend' => SchoolYear::orderBy('starts_on')->get()->map(fn ($y) => [$y->name, $y->enrollments()->count()]),
                'pendingApps' => Application::whereIn('status', ['pending', 'for_review'])->count(),
                'billed' => $billed,
                'collected' => $collected,
                'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0,
                'today' => $today,
                'failing' => $this->failing($sy),
            ];
        }

        if ($u->role === Role::TEACHER) {
            $data['classes'] = ClassSubject::with('subject', 'section.gradeLevel')->where('teacher_id', $u->id)->whereHas('section', fn ($s) => $s->where('school_year_id', $sy?->id))->get();
            $data['todaySlots'] = ScheduleSlot::with('classSubject.subject', 'classSubject.section.gradeLevel')->where('day_of_week', now()->dayOfWeekIso)
                ->whereHas('classSubject', fn ($c) => $c->where('teacher_id', $u->id))->orderBy('starts_at')->get();
            $data['advisory'] = Section::with('gradeLevel')->where('adviser_id', $u->id)->where('school_year_id', $sy?->id)->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])->get();
        }

        return view('dashboard.index', $data);
    }

    /** Students with at least one quarterly grade below passing this school year (top 15). */
    private function failing(?SchoolYear $sy): array
    {
        if (! $sy) {
            return [];
        }
        $passing = (float) Setting::get('passing_grade');
        $out = [];
        $classes = ClassSubject::with('subject', 'section.gradeLevel')->whereHas('section', fn ($s) => $s->where('school_year_id', $sy->id))->has('assessments')->get();
        foreach ($classes as $cs) {
            $terms = $cs->assessments()->distinct()->pluck('term');
            foreach ($terms as $t) {
                foreach (Grading::sheet($cs, (int) $t) as $row) {
                    if ($row['quarterly'] !== null && $row['quarterly'] < $passing) {
                        $out[] = ['student' => $row['student'], 'subject' => $cs->subject->name, 'section' => $cs->section->label(), 'term' => $t, 'grade' => $row['quarterly']];
                    }
                }
            }
            if (count($out) > 50) {
                break;
            }
        }
        usort($out, fn ($a, $b) => $a['grade'] <=> $b['grade']);

        return array_slice($out, 0, 15);
    }
}
