<?php

namespace App\Http\Controllers;

use App\Console\Commands\BackupDatabase;
use App\Models\AuditLog;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Setting;
use App\Models\User;
use App\Services\Tuition;
use App\Support\Admission;
use App\Support\AuditPresenter;
use App\Support\School;
use App\Support\SchoolMail;
use App\Support\TwoStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public const SETTINGS = [
        'School' => ['school_name' => 'School name', 'school_address' => 'Address', 'school_head' => 'Principal / school head (signs report cards)', 'school_email' => 'Email', 'school_phone' => 'Phone'],
        'Grading' => ['use_transmutation' => 'Curve grades with a transmutation table', 'passing_grade' => 'Passing grade', 'honors_with_highest' => 'With Highest Honors – minimum average', 'honors_with_high' => 'With High Honors – minimum average', 'honors_with' => 'With Honors – minimum average'],
        'Attendance & notifications' => ['late_after' => 'Students are late after', 'notify_absent' => 'Notify parents when a student is absent', 'notify_late' => 'Notify parents when a student is late'],
        'Library' => ['library_loan_days' => 'Loan period (days)', 'library_fine_per_day' => 'Fine per day late (₱)'],
    ];

    /** How each setting is edited: toggle switch, time picker or number. */
    public const TYPES = [
        'use_transmutation' => 'toggle', 'notify_absent' => 'toggle', 'notify_late' => 'toggle',
        'late_after' => 'time', 'passing_grade' => 'number', 'honors_with_highest' => 'number', 'honors_with_high' => 'number',
        'honors_with' => 'number', 'library_loan_days' => 'number', 'library_fine_per_day' => 'number', 'school_email' => 'email',
    ];

    public function settings()
    {
        return view('admin.settings', [
            'groups' => self::SETTINGS,
            'levels' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'tuition' => Tuition::current(),
            'year' => SchoolYear::current(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $rules = ['school_name' => 'required|max:150', 'passing_grade' => 'required|numeric|between:50,100', 'late_after' => 'required|date_format:H:i', 'use_transmutation' => 'boolean', 'notify_absent' => 'boolean', 'notify_late' => 'boolean',
            'honors_with_highest' => 'required|numeric', 'honors_with_high' => 'required|numeric', 'honors_with' => 'required|numeric', 'library_loan_days' => 'required|integer|min:1', 'library_fine_per_day' => 'required|numeric|min:0',
            'privacy_notice' => 'required|max:5000', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'deployment_mode' => 'nullable|in:lan,hosted', 'grading_periods' => 'nullable|in:2,3,4', 'school_type' => 'nullable|in:public,private', 'tuition_mode' => 'required_if:school_type,private|in:same,per_level',
            'tuition_all' => 'nullable|required_if:tuition_mode,same|numeric|min:0|max:10000000', 'tuition' => 'array', 'tuition.*' => 'nullable|numeric|min:0|max:10000000'];
        $data = $request->validate($rules + Admission::rules() + collect(self::SETTINGS)->flatMap(fn ($g) => $g)->map(fn () => 'nullable|max:255')->except(array_keys($rules))->all());
        foreach (collect(self::SETTINGS)->flatMap(fn ($g) => array_keys($g))->push('privacy_notice') as $k) {
            $v = (self::TYPES[$k] ?? '') === 'toggle' ? ($request->boolean($k) ? '1' : '0') : ($data[$k] ?? '');
            Setting::put($k, $v);
        }
        if (isset($data['grading_periods']) && (int) $data['grading_periods'] !== School::periods()) {
            $n = (int) $data['grading_periods'];
            $used = \App\Models\Assessment::where('term', '>', $n)->exists();
            if ($used) {
                return back()->withInput()->with('error', 'Some classes already have scores in '.School::term($n + 1, School::periods()).' or later. Remove those first, or keep the current grading periods.');
            }
            GradeLevel::query()->update(['terms' => $n]);
        }
        if (isset($data['deployment_mode']) && $data['deployment_mode'] !== School::mode()) {
            Setting::put('deployment_mode', $data['deployment_mode']);
        }
        if (isset($data['school_type'])) {
            Tuition::save($data['school_type'], $data['tuition_mode'] ?? 'per_level', isset($data['tuition_all']) ? (float) $data['tuition_all'] : null, $data['tuition'] ?? []);
        }
        if ($request->boolean('mail_present')) {
            SchoolMail::save($data);
        }
        if (isset($data['two_step'])) {
            if ($data['two_step'] !== 'off' && ! TwoStep::available()) {
                return back()->withInput()->withErrors(['two_step' => 'Two-step sign-in needs Online mode and working email (SMTP). Set up email first and send a test.']);
            }
            Setting::put('two_step', $data['two_step']);
        }
        if ($request->has('docs') || $request->boolean('docs_present')) {
            Admission::save($request->input('docs', []));
        }
        if ($request->hasFile('logo')) {
            Setting::storeLogo($request->file('logo'));
        }

        return back()->with('ok', 'Settings saved.');
    }

    /** Sends a test email with the saved SMTP settings to the signed-in admin. */
    public function testEmail(Request $request)
    {
        if (! School::allows('email_notifications')) {
            return back()->with('error', 'Email is only available when SmartSchool runs Online.');
        }
        if (! SchoolMail::configured()) {
            return back()->with('error', 'Save the email (SMTP) settings first.');
        }
        SchoolMail::apply();
        try {
            Mail::raw('This is a test from SmartSchool. Email is working.', fn ($m) => $m->to($request->user()->email)->subject('SmartSchool test email'));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The test email could not be sent. Check the server, port, username and password. ('.Str::limit($e->getMessage(), 140).')');
        }

        return back()->with('ok', 'Test email sent to '.$request->user()->email.'. Check the inbox (and spam).');
    }

    /** The school logo (also the browser-tab icon). Versioned URLs (?v=) are cached for a year. */
    public function logo(Request $request)
    {
        $p = Setting::get('logo_path');
        abort_unless($p && Storage::disk('local')->exists($p), 404);

        $versioned = $request->query('v') === substr(md5($p), 0, 8);

        return response()->file(Storage::disk('local')->path($p), [
            'Cache-Control' => $versioned ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function audit(Request $request)
    {
        $q = AuditLog::with('user')->latest('id');
        foreach (['action', 'auditable_type', 'user_id'] as $f) {
            if ($request->filled($f)) {
                $q->where($f, $request->query($f));
            }
        }
        if ($request->filled('id')) {
            $q->where('auditable_id', $request->query('id'));
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from'));
        }
        $logs = $q->paginate(40)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'entries' => AuditPresenter::present($logs->getCollection()),
            'types' => AuditLog::distinct()->whereNotNull('auditable_type')->orderBy('auditable_type')->pluck('auditable_type'),
            'users' => User::whereIn('id', AuditLog::distinct()->select('user_id'))->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function backups()
    {
        $files = collect(Storage::disk('local')->files('backups'))->filter(fn ($f) => BackupDatabase::isBackup($f))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f), 'time' => Storage::disk('local')->lastModified($f)])->sortByDesc('time');

        return view('admin.backups', ['files' => $files]);
    }

    public function runBackup()
    {
        $code = Artisan::call('school:backup');

        return back()->with($code === 0 ? 'ok' : 'error', trim(Artisan::output()));
    }

    public function downloadBackup(string $name)
    {
        abort_unless(BackupDatabase::isBackup($name) && Storage::disk('local')->exists('backups/'.$name), 404);
        AuditLog::record('download', null, [], ['file' => $name], 'Backup downloaded');

        return Storage::disk('local')->download('backups/'.$name);
    }
}
