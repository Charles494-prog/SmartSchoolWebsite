<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    // ------------------------------------------------------------ public

    public function create()
    {
        return view('public.apply', [
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'year' => SchoolYear::current(),
        ]);
    }

    public function store(Request $request)
    {
        $year = SchoolYear::current();
        abort_unless($year && ! $year->enrollment_opens_on?->isFuture(), 503, 'Enrollment is not open yet.');
        $data = $request->validate([
            'grade_level_id' => 'required|exists:grade_levels,id',
            'applicant_type' => 'required|in:new,transferee,returnee',
            'first_name' => 'required|max:80',
            'middle_name' => 'nullable|max:80',
            'last_name' => 'required|max:80',
            'suffix' => 'nullable|max:10',
            'sex' => 'required|in:Male,Female',
            'birthdate' => 'required|date|before:today',
            'birthplace' => 'nullable|max:150',
            'address' => 'required|max:255',
            'previous_school' => 'nullable|required_if:applicant_type,transferee|max:200',
            'guardian_name' => 'required|max:120',
            'guardian_relationship' => 'required|max:30',
            'guardian_phone' => ['required', 'regex:/^(\+?63|0)9\d{9}$/'],
            'guardian_email' => 'required|email|max:150',
            'documents' => 'array',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'consent' => 'accepted',
        ], [
            'guardian_phone.regex' => 'Enter a Philippine mobile number, e.g. 09171234567.',
            'consent.accepted' => 'Please agree to the privacy notice to continue.',
            'documents.*.max' => 'Each file must be 5 MB or smaller.',
            'documents.*.mimes' => 'Upload PDF, JPG or PNG files.',
        ]);
        $files = $request->file('documents', []);
        unset($data['documents'], $data['consent']);
        $data['school_year_id'] = $year->id;
        $data['consent_at'] = now();
        $data['reference_no'] = 'APP-'.now()->format('y').'-'.strtoupper(Str::random(6));
        $app = Application::create($data);
        foreach ($files as $type => $file) {
            if (! $file || ! array_key_exists($type, ApplicationDocument::TYPES)) {
                continue;
            }
            $app->documents()->create([
                'type' => $type,
                'path' => $file->store('applications/'.$app->id, 'local'),
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
        Notification::send(User::whereIn('role', ['admin', 'registrar'])->where('is_active', true)->get(),
            new GeneralNotice('New online application', "{$app->full_name} applied for ".$app->gradeLevel->name.'.', route('applications.show', $app, false)));

        return redirect()->route('apply.done', ['ref' => $app->reference_no]);
    }

    public function done(Request $request)
    {
        return view('public.apply-done', ['ref' => $request->query('ref')]);
    }

    public function status(Request $request)
    {
        $app = null;
        if ($request->filled('reference_no')) {
            $request->validate(['reference_no' => 'required', 'birthdate' => 'required|date']);
            $app = Application::where('reference_no', strtoupper(trim($request->reference_no)))
                ->whereDate('birthdate', $request->birthdate)->first();
            if (! $app) {
                return back()->withInput()->withErrors(['reference_no' => 'No application found with that reference number and birthdate.']);
            }
        }

        return view('public.status', ['app' => $app]);
    }

    // ------------------------------------------------------------ staff

    public function index(Request $request)
    {
        $q = Application::with('gradeLevel')->latest();
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($t = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('last_name', 'like', "%$t%")->orWhere('first_name', 'like', "%$t%")->orWhere('reference_no', 'like', "%$t%"));
        }

        return view('applications.index', [
            'apps' => $q->paginate(25)->withQueryString(),
            'counts' => Application::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(Application $application)
    {
        return view('applications.show', [
            'app' => $application->load('documents', 'gradeLevel', 'schoolYear', 'reviewer'),
            'sections' => Section::where('school_year_id', $application->school_year_id)->where('grade_level_id', $application->grade_level_id)->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])->get(),
        ]);
    }

    public function document(ApplicationDocument $document)
    {
        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function decide(Request $request, Application $application)
    {
        $request->validate([
            'decision' => 'required|in:approve,reject,for_review',
            'remarks' => 'nullable|required_if:decision,reject|max:1000',
            'section_id' => 'nullable|exists:sections,id',
        ], ['remarks.required_if' => 'Write the reason so the family knows what to fix.']);
        if ($application->status === 'approved') {
            return back()->with('error', 'This application was already approved.');
        }
        if ($request->decision === 'approve') {
            [$student, $password] = Enrollment::approve($application, $request->section_id ? Section::find($request->section_id) : null);
            $msg = "Approved. Student no. {$student->student_no} created.";
            if ($password) {
                $msg .= " Parent portal login: {$application->guardian_email} / temporary password: {$password} (give this to the parent; they must change it on first login).";
            }

            return redirect()->route('students.show', $student)->with('ok', $msg);
        }
        $application->update(['status' => $request->decision === 'reject' ? 'rejected' : 'for_review', 'remarks' => $request->remarks, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        if ($request->decision === 'reject') {
            Notification::route('mail', $application->guardian_email)->notify(new GeneralNotice('Application update', "Application {$application->reference_no}: {$request->remarks}", '/apply/status', true));
        }

        return back()->with('ok', 'Application updated.');
    }
}
