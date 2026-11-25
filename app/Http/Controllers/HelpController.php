<?php

namespace App\Http\Controllers;

use App\Console\Commands\BackupDatabase;
use App\Models\AttendanceRecord;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Support\Role;
use App\Support\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HelpController extends Controller
{
    /** "How it works": the school's flow in order, plus who does what. */
    /** Step-by-step: moving SmartSchool from the school Wi-Fi (LAN) to the internet (Hosted). */
    public function goOnline()
    {
        $latest = collect(Storage::disk('local')->files('backups'))
            ->filter(fn ($f) => BackupDatabase::isBackup($f))->sortDesc()->first();

        return view('help.go-online', [
            'mode' => School::mode(),
            'latest' => $latest ? basename($latest) : null,
            'mailReady' => filled(config('mail.mailers.smtp.host')) && config('mail.default') === 'smtp' && filled(config('mail.from.address')),
        ]);
    }

    public function guide()
    {
        return view('help.guide');
    }

    /** Attendance, step by step: 1) pick a class  2) pick how  3) done. */
    public function attendance(Request $request)
    {
        // Daily attendance is taken from the adviser's list; QR scans happen at the gate or in each subject.
        return redirect()->route('attendance.sheet', array_filter(['section_id' => $request->query('section')]));

        $u = $request->user();
        $sections = Section::where('school_year_id', SchoolYear::current()?->id)->with('gradeLevel', 'adviser')
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])
            ->when($u->role === Role::TEACHER, fn ($q) => $q->where('adviser_id', $u->id))
            ->get()->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name]);
        $section = $sections->firstWhere('id', (int) $request->query('section'));
        $done = $section ? AttendanceRecord::where('section_id', $section->id)->whereNull('class_subject_id')->whereDate('date', today())->count() : 0;

        return view('help.attendance', ['sections' => $sections, 'section' => $section, 'done' => $done]);
    }

    /** One place to print students' QR ID cards. */
    public function qrCards(Request $request)
    {
        $sections = Section::where('school_year_id', SchoolYear::current()?->id)->with('gradeLevel')
            ->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])->get()->sortBy(fn ($s) => [$s->gradeLevel->sort_order, $s->name]);
        $students = $request->filled('q') ? Student::where(fn ($w) => $w->where('last_name', 'like', '%'.$request->q.'%')->orWhere('first_name', 'like', '%'.$request->q.'%')->orWhere('student_no', 'like', '%'.$request->q.'%'))->limit(20)->get() : collect();

        return view('help.qr-cards', ['sections' => $sections, 'students' => $students]);
    }
}
