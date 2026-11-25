<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use App\Services\Timetable;
use App\Support\DemoNames;
use App\Support\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Rebuild the DEMO timetable: home rooms per grade block, then a conflict-free, staggered Mon–Fri schedule. */
class TimetableSchedule extends Command
{
    protected $signature = 'timetable:schedule {--reset-demo : Required. Replaces every class time of the current school year} {--force : Skip the confirmation}';

    protected $description = 'Regenerate the demo timetable with no double-booked rooms, teachers or sections';

    public function handle(): int
    {
        if (! $this->option('reset-demo')) {
            $this->error('Add --reset-demo to confirm you want to replace the demo timetable.');

            return self::FAILURE;
        }
        $sy = SchoolYear::current();
        if (! $sy) {
            $this->error('No current school year.');

            return self::FAILURE;
        }
        $real = Student::where(fn ($q) => $q->where('lrn', 'not like', '14000%')->orWhereNull('lrn'))->count();
        if ($real > 0) {
            $this->warn("WARNING: $real student(s) that are not demo data exist. Their sections' class times will be replaced too.");
        }
        $this->line('This replaces every class time in '.$sy->name.', gives each section its own home room in its grade block,');
        $this->line('and (demo only) moves a class to another teacher when its teacher has no free period.');
        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            return self::FAILURE;
        }
        $added = self::ensureTeachers($sy);
        Timetable::assignHomeRooms($sy);
        [$placed, $failed] = Timetable::schedule($sy, true);
        AuditLog::record('timetable_reset', null, [], ['placed' => $placed, 'failed' => count($failed), 'teachers_added' => $added], 'Demo timetable regenerated');
        $this->info("Placed $placed classes.".($added ? " Added $added demo teacher(s) so no one is double-booked." : ''));
        foreach ($failed as $f) {
            $this->warn('Could not place: '.$f);
        }

        return $this->call('timetable:check');
    }

    /** Demo data needs enough teachers: each teaches at most 7 of the 8 periods. */
    public static function ensureTeachers(SchoolYear $sy): int
    {
        $classes = ClassSubject::whereHas('section', fn ($q) => $q->where('school_year_id', $sy->id))->count();
        $have = User::where('role', Role::TEACHER)->where('is_active', true)->count();
        $need = (int) ceil($classes / (count(Timetable::PERIODS) - 1));
        $added = 0;
        for ($k = 0; $have + $added < $need; $k++) {
            $email = 'teacher'.(100 + $k).'@school.test';
            [$f, , $l] = DemoNames::for(1001 + $k * 2);
            if (User::where('email', $email)->exists() || User::where('name', "$f $l")->exists()) {
                continue;
            }
            User::create(['name' => "$f $l", 'email' => $email, 'password' => Hash::make('password'), 'role' => Role::TEACHER, 'is_active' => true]);
            $added++;
        }

        return $added;
    }
}
