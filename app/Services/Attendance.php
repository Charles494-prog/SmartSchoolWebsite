<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\ClassSubject;
use App\Models\Setting;
use App\Models\Student;
use App\Notifications\AttendanceAlert;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class Attendance
{
    /**
     * Daily check-in by QR token (or student no / LRN typed by a USB scanner).
     *
     * @return array{ok: bool, status: string, message: string, student: ?Student}
     */
    public static function checkIn(string $code, ?Carbon $at = null, ?ClassSubject $class = null): array
    {
        $at ??= now();
        $code = trim($code);
        $student = Student::where('qr_token', $code)->orWhere('student_no', $code)->orWhere('lrn', $code)->first();
        if (! $student) {
            return ['ok' => false, 'status' => 'unknown', 'message' => 'QR code not recognized.', 'student' => null];
        }
        if ($student->status !== 'active') {
            return ['ok' => false, 'status' => 'inactive', 'message' => 'Student is not active ('.$student->status.').', 'student' => $student];
        }
        if ($class) {
            return self::classCheckIn($student, $class, $at);
        }
        $existing = AttendanceRecord::where('student_id', $student->id)->whereDate('date', $at->toDateString())->whereNull('class_subject_id')->first();
        if ($existing && in_array($existing->status, ['present', 'late'], true)) {
            // Second scan at the gate = going home. Ignore accidental repeat scans within 2 minutes.
            if ($existing->time_out) {
                return ['ok' => true, 'status' => 'already', 'message' => 'Already checked out at '.ampm($existing->time_out).'.', 'student' => $student];
            }
            if ($existing->time_in && Carbon::parse($at->toDateString().' '.$existing->time_in)->diffInMinutes($at) < 2) {
                $outFrom = Carbon::parse($at->toDateString().' '.$existing->time_in)->addMinutes(2)->format('H:i');

                return ['ok' => true, 'status' => 'already', 'message' => 'Already checked in at '.ampm($existing->time_in).'. Scan again from '.ampm($outFrom).' to check OUT.', 'student' => $student];
            }
            $existing->update(['time_out' => $at->format('H:i:s')]);

            return ['ok' => true, 'status' => 'out', 'message' => 'Checked OUT at '.ampm($at->format('H:i')), 'student' => $student];
        }
        $status = self::isLate($at) ? 'late' : 'present';
        $section = $student->currentEnrollment()?->section_id;
        $rec = AttendanceRecord::updateOrCreate(
            ['student_id' => $student->id, 'date' => $at->toDateString(), 'class_subject_id' => null],
            ['section_id' => $section, 'status' => $status, 'time_in' => $at->format('H:i:s'), 'method' => 'qr', 'recorded_by' => auth()->id()],
        );
        if ($status === 'late') {
            self::notifyParents($rec);
        }

        return ['ok' => true, 'status' => $status, 'message' => ($status === 'late' ? 'Checked IN – LATE' : 'Checked IN').' at '.ampm($at->format('H:i')), 'student' => $student];
    }

    /** Scan inside a classroom: one record per student, subject and day. Late = 15+ minutes after the class starts. */
    private static function classCheckIn(Student $student, ClassSubject $class, Carbon $at): array
    {
        if ($student->currentEnrollment()?->section_id !== $class->section_id) {
            return ['ok' => false, 'status' => 'wrong_class', 'message' => 'Not in this class ('.$class->section->label().').', 'student' => $student];
        }
        $existing = AttendanceRecord::where('student_id', $student->id)->whereDate('date', $at->toDateString())->where('class_subject_id', $class->id)->first();
        if ($existing) {
            return ['ok' => true, 'status' => 'already', 'message' => 'Already checked in to '.$class->subject->name.'.', 'student' => $student];
        }
        $slot = $class->slots()->where('day_of_week', $at->dayOfWeekIso)->orderBy('starts_at')->first();
        $late = $slot && $at->format('H:i:s') > Carbon::parse($slot->starts_at)->addMinutes(15)->format('H:i:s');
        AttendanceRecord::create(['student_id' => $student->id, 'date' => $at->toDateString(), 'class_subject_id' => $class->id, 'section_id' => $class->section_id,
            'status' => $late ? 'late' : 'present', 'time_in' => $at->format('H:i:s'), 'method' => 'qr', 'recorded_by' => auth()->id()]);

        return ['ok' => true, 'status' => $late ? 'late' : 'present', 'message' => $class->subject->name.($late ? ' – LATE' : ' – present'), 'student' => $student];
    }

    public static function isLate(Carbon $at): bool
    {
        return $at->format('H:i') > (Setting::get('late_after') ?: '23:59');
    }

    /** Email + in-app notice to the student's guardians when absent or late. */
    public static function notifyParents(AttendanceRecord $rec): void
    {
        $key = $rec->status === 'absent' ? 'notify_absent' : ($rec->status === 'late' ? 'notify_late' : null);
        if (! $key || Setting::get($key) !== '1') {
            return;
        }
        $users = $rec->student->guardians()->whereNotNull('user_id')->with('user')->get()->pluck('user')->filter(fn ($u) => $u && $u->is_active);
        Notification::send($users, new AttendanceAlert($rec));
    }
}
