<?php

namespace App\Support;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns raw audit rows into plain sentences: "Ana Cruz changed Juan Dela Cruz's section from Rizal to Mabini".
 * Record names and linked IDs are looked up in batches (one query per type per page).
 */
class AuditPresenter
{
    public const TYPE_LABELS = [
        'User' => 'user account', 'Student' => 'student', 'Enrollment' => 'enrollment', 'Section' => 'section', 'ClassSubject' => 'class',
        'Score' => 'score', 'Assessment' => 'assessment', 'AttendanceRecord' => 'attendance', 'FeeItem' => 'fee', 'StudentAccount' => 'student account',
        'Payment' => 'payment', 'SchoolYear' => 'school year', 'GradeLevel' => 'grade level', 'Subject' => 'subject', 'Room' => 'room',
        'Application' => 'application', 'Announcement' => 'announcement', 'Event' => 'event', 'Employee' => 'employee', 'Payroll' => 'payroll',
        'Guardian' => 'guardian', 'Setting' => 'setting', 'ScheduleSlot' => 'schedule', 'Transfer' => 'transfer', 'Discount' => 'discount',
    ];

    public const ACTIONS = [
        'created' => 'Added', 'updated' => 'Changed', 'deleted' => 'Deleted', 'login' => 'Logged in', 'export' => 'Exported data',
        'download' => 'Downloaded', 'backup' => 'Backup', 'reminders' => 'Sent reminders',
    ];

    private const HIDDEN = ['password', 'remember_token', 'qr_token', 'updated_at', 'created_at', 'id', 'two_factor_secret', 'two_factor_recovery_codes'];

    private const FK_ALIASES = ['adviser' => 'User', 'teacher' => 'User', 'user' => 'User', 'approved_by' => 'User', 'recorded_by' => 'User', 'next_grade_level' => 'GradeLevel'];

    /** @param  Collection<int, AuditLog>  $logs */
    public static function present(Collection $logs): Collection
    {
        $names = self::lookupNames($logs);

        return $logs->map(function (AuditLog $l) use ($names) {
            $type = $l->auditable_type;
            $subject = $type && $l->auditable_id ? ($names[$type][$l->auditable_id] ?? null) : null;
            $kind = self::TYPE_LABELS[$type] ?? Str::lower(Str::headline((string) $type));
            $who = $l->user?->name ?? 'System';

            $sentence = match ($l->action) {
                'created' => "added {$kind}",
                'updated' => "changed {$kind}",
                'deleted' => "deleted {$kind}",
                'login' => 'logged in',
                'export' => 'exported the personal data of',
                'download' => 'downloaded',
                'backup' => 'made a database backup',
                'reminders' => 'sent payment reminders',
                default => Str::lower(Str::headline($l->action)),
            };
            if ($l->action === 'login') {
                $subject = null;
            }

            $changes = [];
            $new = (array) ($l->new_values ?? []);
            $old = (array) ($l->old_values ?? []);
            $fields = $l->action === 'deleted' ? array_keys($old) : array_keys($new);
            foreach ($fields as $field) {
                if (in_array($field, self::HIDDEN, true) || str_contains((string) $field, 'password')) {
                    if ($field === 'password' && $l->action === 'updated') {
                        $changes[] = ['label' => 'Password', 'old' => null, 'new' => 'changed', 'plain' => true];
                    }

                    continue;
                }
                $changes[] = [
                    'label' => self::fieldLabel((string) $field),
                    'old' => $l->action === 'updated' ? self::value((string) $field, $old[$field] ?? null, $names) : null,
                    'new' => self::value((string) $field, $l->action === 'deleted' ? ($old[$field] ?? null) : ($new[$field] ?? null), $names),
                    'plain' => $l->action !== 'updated',
                ];
            }

            return [
                'log' => $l,
                'who' => $who,
                'initials' => collect(explode(' ', $who))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''),
                'action' => $l->action,
                'sentence' => $sentence,
                'subject' => $subject ?? ($l->action === 'login' ? null : ($l->description ?: ($l->auditable_id ? "#{$l->auditable_id}" : null))),
                'changes' => $changes,
                'time' => $l->created_at,
            ];
        });
    }

    public static function fieldLabel(string $field): string
    {
        $field = preg_replace('/_id$/', '', $field);

        return Str::ucfirst(Str::lower(Str::headline($field)));
    }

    private static function value(string $field, mixed $v, array $names): string
    {
        if ($v === null || $v === '') {
            return 'empty';
        }
        if (is_bool($v) || preg_match('/^(is_|must_|has_|notify_|use_)/', $field)) {
            return in_array($v, [true, 1, '1', 'true'], true) ? 'Yes' : 'No';
        }
        if (str_ends_with($field, '_path')) {
            return 'a new file';
        }
        if (str_ends_with($field, '_id') && is_numeric($v)) {
            $type = self::fkType($field);

            return $names[$type][(int) $v] ?? "#{$v}";
        }
        if (is_array($v)) {
            return Str::limit(json_encode($v, JSON_UNESCAPED_UNICODE), 80);
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', $v)) {
            try {
                $d = Carbon::parse($v);

                return strlen($v) > 10 && $d->format('H:i') !== '00:00' ? $d->format('M j, Y g:i A') : $d->format('M j, Y');
            } catch (\Throwable) {
            }
        }
        if (is_string($v) && preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $v)) {
            return ampm($v);
        }

        return Str::limit((string) $v, 80);
    }

    private static function fkType(string $field): string
    {
        $base = preg_replace('/_id$/', '', $field);

        return self::FK_ALIASES[$base] ?? Str::studly($base);
    }

    /** @return array<string, array<int, string>> type => [id => readable name] */
    private static function lookupNames(Collection $logs): array
    {
        $want = [];
        foreach ($logs as $l) {
            if ($l->auditable_type && $l->auditable_id) {
                $want[$l->auditable_type][] = (int) $l->auditable_id;
            }
            foreach ([(array) $l->old_values, (array) $l->new_values] as $vals) {
                foreach ($vals as $k => $v) {
                    if (is_string($k) && str_ends_with($k, '_id') && is_numeric($v)) {
                        $want[self::fkType($k)][] = (int) $v;
                    }
                }
            }
        }
        $out = [];
        foreach ($want as $type => $ids) {
            $class = 'App\\Models\\'.$type;
            if (! class_exists($class)) {
                continue;
            }
            $query = $class::query()->whereIn('id', array_unique($ids));
            foreach (['section' => 'Enrollment', 'gradeLevel' => 'Section'] as $rel => $forType) {
                if ($type === $forType) {
                    $query->with($rel);
                }
            }
            if ($type === 'Enrollment') {
                $query->with('student', 'section.gradeLevel');
            }
            if (in_array($type, ['ClassSubject'], true)) {
                $query->with('subject', 'section.gradeLevel');
            }
            if (in_array($type, ['Score', 'StudentAccount', 'AttendanceRecord', 'Payment'], true) && method_exists($class, 'student')) {
                $query->with('student');
            }
            foreach ($query->get() as $m) {
                $out[$type][$m->id] = self::nameOf($type, $m);
            }
        }

        return $out;
    }

    private static function nameOf(string $type, $m): string
    {
        return match ($type) {
            'Enrollment' => trim(($m->student?->full_name ?? 'Student').' · '.($m->section ? $m->section->label() : 'no section')),
            'ClassSubject' => trim(($m->subject?->name ?? 'Class').' · '.($m->section?->label() ?? '')),
            'Section' => method_exists($m, 'label') && $m->relationLoaded('gradeLevel') ? $m->label() : (string) $m->name,
            default => (string) ($m->full_name ?? $m->name ?? $m->title ?? $m->or_number ?? $m->reference_no ?? ($m->student?->full_name ?? '#'.$m->id)),
        };
    }
}
