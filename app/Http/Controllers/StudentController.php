<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\ClinicVisit;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\GuidanceRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing;
use App\Services\Grading;
use App\Support\Photo;
use App\Support\Role;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    private const EDITORS = [Role::ADMIN, Role::REGISTRAR];

    private function authorizeView(Student $student): void
    {
        abort_unless(auth()->user()->canViewStudent($student), 403);
    }

    private function authorizeEdit(): void
    {
        abort_unless(auth()->user()->hasRole(...self::EDITORS), 403);
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $sy = SchoolYear::current();
        $q = Student::query()->with(['enrollments' => fn ($e) => $e->where('school_year_id', $sy?->id)->with('section', 'gradeLevel')]);
        if ($u->role === Role::TEACHER) {
            $q->whereHas('enrollments', fn ($e) => $e->whereIn('section_id', $u->teacherSectionIds()));
        }
        if ($t = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('last_name', 'like', "%$t%")->orWhere('first_name', 'like', "%$t%")->orWhere('student_no', 'like', "%$t%")->orWhere('lrn', 'like', "%$t%"));
        }
        if ($request->filled('section_id')) {
            $q->whereHas('enrollments', fn ($e) => $e->where('section_id', $request->section_id));
        }
        if ($request->filled('grade_level_id')) {
            $q->whereHas('enrollments', fn ($e) => $e->where('school_year_id', $sy?->id)->where('grade_level_id', $request->grade_level_id));
        }
        $q->where('status', $request->query('status', 'active'));
        if ($request->filled('sex')) {
            $q->where('sex', $request->sex);
        }

        return view('students.index', [
            'students' => $q->orderBy('last_name')->orderBy('first_name')->paginate(30)->withQueryString(),
            'sections' => Section::where('school_year_id', $sy?->id)->with('gradeLevel')->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'canEdit' => $u->hasRole(...self::EDITORS),
        ]);
    }

    /** Store a square, ID-sized (600×600) copy of the uploaded 1×1 photo. */
    private function savePhoto(Request $request, Student $student): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }
        $student->update(['photo_path' => Photo::storeSquare($request->file('photo')->getRealPath(), 'photos/'.$student->id.'-'.time().'.jpg')]);
    }

    public function photo(Student $student)
    {
        $this->authorizeView($student);
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return response()->file(Storage::disk('local')->path($student->photo_path), ['Cache-Control' => 'private, max-age=600']);
    }

    /** Quick search for the Ctrl+K palette and the detail drawer. */
    public function quick(Request $request)
    {
        $u = $request->user();
        $t = trim((string) $request->query('q'));
        if ($request->filled('id')) {
            $s = Student::findOrFail($request->id);
            abort_unless($u->canViewStudent($s), 403);
            $e = $s->currentEnrollment();
            $g = $s->guardians()->first();

            return response()->json([
                'id' => $s->id, 'name' => $s->full_name, 'student_no' => $s->student_no, 'lrn' => $s->lrn, 'sex' => $s->sex, 'age' => $s->age,
                'class' => $e ? $e->gradeLevel->name.($e->section ? ' – '.$e->section->name : '') : 'Not enrolled this year',
                'status' => $s->status, 'address' => $s->address,
                'guardian' => $g ? $g->name.' ('.$g->pivot->relationship.') '.$g->phone : null,
                'absences' => $s->attendance()->where('status', 'absent')->count(),
                'average' => $e ? Grading::reportCard($e)['general_average'] : null,
                'balance' => $e?->account && $u->hasRole(Role::ADMIN, Role::CASHIER, Role::REGISTRAR) ? Billing::peso($e->account->balance()) : null,
                'url' => route('students.show', $s),
            ]);
        }
        if (mb_strlen($t) < 2 || ! $u->isStaff()) {
            return response()->json([]);
        }
        $q = Student::query()->where(fn ($w) => $w->where('last_name', 'like', "%$t%")->orWhere('first_name', 'like', "%$t%")->orWhere('student_no', 'like', "%$t%")->orWhere('lrn', 'like', "%$t%"));
        if ($u->role === Role::TEACHER) {
            $q->whereHas('enrollments', fn ($e) => $e->whereIn('section_id', $u->teacherSectionIds()));
        }

        return response()->json($q->orderBy('last_name')->limit(8)->get()->map(fn ($s) => ['label' => $s->full_name, 'hint' => $s->student_no, 'url' => route('students.show', $s)]));
    }

    private function rules(?Student $s = null): array
    {
        return [
            'lrn' => ['nullable', 'digits:12', Rule::unique('students', 'lrn')->ignore($s?->id)],
            'first_name' => 'required|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name' => 'required|max:80',
            'suffix' => 'nullable|max:10',
            'sex' => 'required|in:Male,Female',
            'birthdate' => 'required|date|before:today',
            'birthplace' => 'nullable|max:150',
            'address' => 'nullable|max:255',
            'religion' => 'nullable|max:80',
            'nationality' => 'nullable|max:60',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:150',
            'medical_notes' => 'nullable|max:2000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'status' => 'sometimes|in:active,transferred,graduated,dropped',
        ];
    }

    public function create()
    {
        $this->authorizeEdit();
        $sy = SchoolYear::current();

        return view('students.form', [
            'student' => null,
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'sections' => Section::where('school_year_id', $sy?->id)->with('gradeLevel')->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeEdit();
        $data = $request->validate($this->rules() + [
            'grade_level_id' => 'nullable|exists:grade_levels,id',
            'section_id' => 'nullable|exists:sections,id',
        ], $this->lrnMessages($request->input('lrn')));
        $student = DB::transaction(function () use ($data) {
            $student = Student::create(collect($data)->except(['grade_level_id', 'section_id', 'photo'])->all());
            $this->savePhoto(request(), $student);
            if (! empty($data['grade_level_id']) && ($sy = SchoolYear::current())) {
                Enrollment::create(['student_id' => $student->id, 'school_year_id' => $sy->id, 'grade_level_id' => $data['grade_level_id'], 'section_id' => $data['section_id'] ?? null, 'enrolled_on' => now()]);
            }

            return $student;
        });

        return redirect()->route('students.show', $student)->with('ok', 'Student saved. Next: add a parent/guardian below.');
    }

    public function show(Student $student)
    {
        $this->authorizeView($student);
        $u = auth()->user();
        $student->load(['guardians.user', 'enrollments.schoolYear', 'enrollments.gradeLevel', 'enrollments.section', 'enrollments.account', 'transfers', 'user']);
        $current = $student->currentEnrollment();

        return view('students.show', [
            's' => $student,
            'current' => $current,
            'report' => $current ? Grading::reportCard($current) : null,
            'attendance' => AttendanceRecord::where('student_id', $student->id)->whereNull('class_subject_id')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'recentAttendance' => AttendanceRecord::where('student_id', $student->id)->whereNull('class_subject_id')->latest('date')->limit(10)->get(),
            'clinic' => $u->hasRole(Role::ADMIN, Role::NURSE) ? ClinicVisit::where('student_id', $student->id)->latest('visited_at')->get() : null,
            'guidance' => $u->hasRole(Role::GUIDANCE) ? $student->hasMany(GuidanceRecord::class)->latest('recorded_on')->get() : null,
            'canEdit' => $u->hasRole(...self::EDITORS),
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'years' => SchoolYear::orderByDesc('starts_on')->pluck('name', 'id'),
            'sections' => Section::with('gradeLevel', 'schoolYear')->orderByDesc('school_year_id')->get()->mapWithKeys(fn ($s) => [$s->id => $s->schoolYear->name.' · '.$s->label()]),
            'showMedical' => $u->hasRole(Role::ADMIN, Role::NURSE, Role::REGISTRAR, Role::TEACHER, Role::GUIDANCE),
        ]);
    }

    public function edit(Student $student)
    {
        $this->authorizeEdit();

        return view('students.form', ['student' => $student, 'levels' => [], 'sections' => []]);
    }

    public function update(Request $request, Student $student)
    {
        $this->authorizeEdit();
        $student->update(collect($request->validate($this->rules($student), $this->lrnMessages($request->input('lrn'), $student->id)))->except('photo')->all());
        $this->savePhoto($request, $student);

        return redirect()->route('students.show', $student)->with('ok', 'Student updated.');
    }

    public function addGuardian(Request $request, Student $student)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'name' => 'required|max:120',
            'relationship' => 'required|max:30',
            'phone' => 'nullable|max:30',
            'email' => 'nullable|email|max:150',
            'occupation' => 'nullable|max:100',
            'is_primary' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'create_account' => 'boolean',
        ]);
        $password = null;
        DB::transaction(function () use ($data, $student, &$password) {
            $user = null;
            if (! empty($data['email'])) {
                $user = User::where('email', $data['email'])->first();
                if (! $user && ! empty($data['create_account'])) {
                    $password = Str::password(10, symbols: false);
                    $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $password, 'role' => Role::PARENT, 'must_change_password' => true]);
                }
            }
            $guardian = $user ? Guardian::firstOrCreate(['user_id' => $user->id], ['name' => $data['name']]) : new Guardian;
            $guardian->fill(collect($data)->only(['name', 'phone', 'email', 'occupation'])->all())->save();
            $student->guardians()->syncWithoutDetaching([$guardian->id => [
                'relationship' => $data['relationship'],
                'is_primary' => $data['is_primary'] ?? false,
                'is_emergency_contact' => $data['is_emergency_contact'] ?? false,
            ]]);
        });

        return back()->with('ok', 'Guardian added.'.($password ? " Parent portal login: {$data['email']} / temporary password: {$password}" : ''));
    }

    public function removeGuardian(Student $student, Guardian $guardian)
    {
        $this->authorizeEdit();
        $student->guardians()->detach($guardian->id);

        return back()->with('ok', 'Guardian removed from this student.');
    }

    /** Create (or reset) the student's own portal login. */
    public function studentAccount(Request $request, Student $student)
    {
        $this->authorizeEdit();
        $request->validate(['email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student->user_id)]]);
        $password = Str::password(10, symbols: false);
        if ($student->user) {
            $student->user->update(['email' => $request->email, 'password' => $password, 'must_change_password' => true, 'is_active' => true]);
        } else {
            $user = User::create(['name' => $student->first_name.' '.$student->last_name, 'email' => $request->email, 'password' => $password, 'role' => Role::STUDENT, 'must_change_password' => true]);
            $student->update(['user_id' => $user->id]);
        }

        return back()->with('ok', "Student portal login: {$request->email} / temporary password: {$password}");
    }

    public function enroll(Request $request, Student $student)
    {
        $this->authorizeEdit();
        $data = $request->validate([
            'school_year_id' => 'required|exists:school_years,id',
            'grade_level_id' => 'required|exists:grade_levels,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);
        if ($data['section_id'] ?? null) {
            $sec = Section::find($data['section_id']);
            if ($sec->school_year_id != $data['school_year_id'] || $sec->grade_level_id != $data['grade_level_id']) {
                return back()->withErrors(['section_id' => 'That section belongs to a different school year or grade level.']);
            }
        }
        Enrollment::updateOrCreate(['student_id' => $student->id, 'school_year_id' => $data['school_year_id']], $data + ['enrolled_on' => now(), 'status' => 'enrolled']);
        $student->update(['status' => 'active']);

        return back()->with('ok', 'Enrollment saved.');
    }

    public function transferOut(Request $request, Student $student)
    {
        $this->authorizeEdit();
        $data = $request->validate(['school_name' => 'required|max:200', 'transfer_date' => 'required|date', 'reason' => 'nullable|max:1000']);
        DB::transaction(function () use ($student, $data) {
            $student->transfers()->create($data + ['direction' => 'out']);
            $student->update(['status' => 'transferred']);
            $student->currentEnrollment()?->update(['status' => 'transferred_out']);
        });

        return back()->with('ok', 'Student marked as transferred out. You can print the permanent record for the new school.');
    }

    public function idCard(Student $student)
    {
        $this->authorizeView($student);
        $e = $student->currentEnrollment();

        return Pdf::loadView('pdf.id-card', ['students' => collect([$student]), 'enrollments' => [$student->id => $e]])->setPaper('a4')->stream('ID-'.$student->student_no.'.pdf');
    }

    public function sectionIdCards(Section $section)
    {
        abort_unless(auth()->user()->hasRole(Role::ADMIN, Role::REGISTRAR) || $section->adviser_id === auth()->id(), 403);
        Setting::put('qr_printed', '1');
        $students = $section->students()->get();
        $enr = Enrollment::where('section_id', $section->id)->with('section', 'gradeLevel')->get()->keyBy('student_id');

        return Pdf::loadView('pdf.id-card', ['students' => $students, 'enrollments' => $enr])->setPaper('a4')->stream('ID-cards-'.Str::slug($section->name).'.pdf');
    }

    /** SF9 – Report card for one enrollment. */
    public function reportCard(Enrollment $enrollment)
    {
        $this->authorizeView($enrollment->student);
        $enrollment->load('student', 'section.adviser', 'gradeLevel', 'schoolYear');
        $att = AttendanceRecord::where('student_id', $enrollment->student_id)->whereNull('class_subject_id')
            ->whereBetween('date', [$enrollment->schoolYear->starts_on, $enrollment->schoolYear->ends_on])
            ->get()->groupBy(fn ($r) => $r->date->format('Y-m'));

        return Pdf::loadView('pdf.sf9', ['e' => $enrollment, 'rc' => Grading::reportCard($enrollment), 'attendance' => $att])
            ->setPaper('a4', 'landscape')->stream('Report-card-'.$enrollment->student->student_no.'.pdf');
    }

    /** SF10 – Permanent academic record (all school years). */
    public function permanentRecord(Student $student)
    {
        abort_unless(auth()->user()->hasRole(Role::ADMIN, Role::REGISTRAR), 403);
        $records = $student->enrollments()->with('schoolYear', 'gradeLevel', 'section.adviser')->get()->sortBy('schoolYear.starts_on')
            ->map(fn ($e) => ['e' => $e, 'rc' => Grading::reportCard($e)]);

        return Pdf::loadView('pdf.sf10', ['s' => $student->load('guardians'), 'records' => $records])->setPaper('a4')->stream('Permanent-record-'.$student->student_no.'.pdf');
    }

    /** Data Privacy Act: give the data subject a copy of their personal data. */
    public function export(Student $student)
    {
        abort_unless(auth()->user()->hasRole(Role::ADMIN, Role::REGISTRAR) || (auth()->user()->canViewStudent($student) && auth()->user()->hasRole(Role::PARENT, Role::STUDENT)), 403);
        $data = [
            'exported_at' => now()->toIso8601String(),
            'school' => Setting::get('school_name'),
            'student' => $student->makeHidden(['qr_token'])->toArray(),
            'guardians' => $student->guardians()->get()->map->only(['name', 'phone', 'email', 'occupation'])->all(),
            'enrollments' => $student->enrollments()->with('schoolYear:id,name', 'gradeLevel:id,name', 'section:id,name')->get()->toArray(),
            'attendance' => $student->attendance()->get(['date', 'status', 'time_in', 'remarks'])->toArray(),
            'grades' => $student->enrollments()->get()->map(fn ($e) => Grading::reportCard($e))->all(),
        ];
        AuditLog::record('export', $student, description: 'Personal data export');

        return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'student-data-'.$student->student_no.'.json', ['Content-Type' => 'application/json']);
    }

    private function lrnMessages(?string $lrn, ?int $ignore = null): array
    {
        $other = $lrn ? Student::where('lrn', $lrn)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->first() : null;

        return ['lrn.unique' => 'This LRN already belongs to '.($other?->full_name ?? 'another student').' – every LRN must be unique.', 'lrn.digits' => 'An LRN has exactly 12 digits.'];
    }

    /** Live check while staff type an LRN: is it already someone else's? */
    public function lrnCheck(Request $request)
    {
        abort_unless($request->user()->hasRole(...self::EDITORS), 403);
        $lrn = preg_replace('/\D/', '', (string) $request->query('lrn'));
        $other = Student::where('lrn', $lrn)->when($request->query('ignore'), fn ($q, $id) => $q->where('id', '!=', (int) $id))->first();

        return response()->json(['taken' => $other?->full_name]);
    }

    /** Staff page to enter LRNs for many students at once (students never type their own LRN). */
    public function lrnIndex(Request $request)
    {
        abort_unless($request->user()->hasRole(...self::EDITORS), 403);
        $missing = $request->query('show') !== 'all';
        $students = Student::with('enrollments.section.gradeLevel')->when($missing, fn ($q) => $q->where(fn ($w) => $w->whereNull('lrn')->orWhere('lrn', '')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('last_name', 'like', '%'.$request->query('q').'%')->orWhere('first_name', 'like', '%'.$request->query('q').'%')))
            ->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        return view('students.lrn', ['students' => $students, 'missing' => $missing, 'count' => Student::where(fn ($w) => $w->whereNull('lrn')->orWhere('lrn', ''))->count()]);
    }

    public function lrnSave(Request $request, Student $student)
    {
        abort_unless($request->user()->hasRole(...self::EDITORS), 403);
        $other = Student::where('lrn', $request->input('lrn'))->where('id', '!=', $student->id)->first();
        $request->validate(['lrn' => ['nullable', 'digits:12', Rule::unique('students', 'lrn')->ignore($student->id)]],
            ['lrn.unique' => 'This LRN already belongs to '.($other?->full_name ?? 'another student').'.', 'lrn.digits' => 'An LRN has exactly 12 digits.']);
        $student->update(['lrn' => $request->input('lrn') ?: null]);

        return back()->with('ok', 'LRN saved for '.$student->full_name.'.');
    }
}
