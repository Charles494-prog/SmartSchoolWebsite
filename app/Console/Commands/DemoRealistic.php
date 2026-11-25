<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Allocator;
use App\Services\Billing;
use App\Services\Timetable;
use App\Support\DemoNames;
use App\Support\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic demo data for the CURRENT school year:
 * 4 sections per grade (Grade 1–12), rooms N01–N10 per grade (sections use N01–N04),
 * subject teachers by specialization, ~30 students per section, and a DepEd-style weekly timetable
 * (Math 4×/week, Values 2×/week…) with no room/teacher/section conflicts.
 * Real (non-demo) students are kept; they are placed into sections like everyone else.
 */
class DemoRealistic extends Command
{
    protected $signature = 'demo:realistic {--per-section=30 : Students per section} {--force : Skip the confirmation}';

    protected $description = 'Build realistic demo sections, rooms, teachers, students and a DepEd-style timetable for the current school year';

    public const SECTION_NAMES = [
        1 => ['Sampaguita', 'Ilang-Ilang', 'Rosal', 'Camia'], 2 => ['Gumamela', 'Orchid', 'Jasmine', 'Dahlia'], 3 => ['Narra', 'Molave', 'Acacia', 'Mahogany'],
        4 => ['Diamond', 'Emerald', 'Ruby', 'Sapphire'], 5 => ['Jade', 'Pearl', 'Topaz', 'Opal'], 6 => ['Mercury', 'Venus', 'Mars', 'Jupiter'],
        7 => ['Rizal', 'Bonifacio', 'Mabini', 'Luna'], 8 => ['Aguinaldo', 'Del Pilar', 'Jacinto', 'Silang'], 9 => ['Dagohoy', 'Lapu-Lapu', 'Sultan Kudarat', 'Soliman'],
        10 => ['Newton', 'Einstein', 'Curie', 'Galileo'], 11 => ['STEM A', 'STEM B', 'ABM', 'HUMSS'], 12 => ['STEM A', 'STEM B', 'ABM', 'HUMSS'],
    ];

    private const TEACHER_LOAD = 24;   // teaching hours a week per teacher

    public function handle(): int
    {
        $sy = SchoolYear::current();
        if (! $sy) {
            $this->error('No current school year.');

            return self::FAILURE;
        }
        $this->line("This rebuilds sections, rooms, class teachers and the timetable for {$sy->name}, and adds demo students/teachers.");
        $this->line('Earlier school years are not touched. A copy of the database is saved first (SQLite).');
        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            return self::FAILURE;
        }
        if (config('database.default') === 'sqlite' && is_file($db = config('database.connections.sqlite.database'))) {
            copy($db, storage_path('app/database-backup-before-realistic-'.now()->format('Ymd-His').'.sqlite'));
        }
        $per = max(10, min(45, (int) $this->option('per-section')));
        $levels = GradeLevel::orderBy('sort_order')->get()->filter(fn ($g) => Timetable::gradeNumber($g) >= 1 && $g->subjects()->exists());

        DB::transaction(function () use ($sy, $levels, $per) {
            // 1. Rooms and 4 sections per grade
            foreach ($levels as $g) {
                $n = Timetable::gradeNumber($g);
                Timetable::generateRooms($g, 10);
                $mine = Section::where('school_year_id', $sy->id)->where('grade_level_id', $g->id)->orderBy('id')->get();
                foreach (self::SECTION_NAMES[$n] ?? [] as $k => $name) {
                    if ($mine->count() >= 4) {
                        break;
                    }
                    $label = Section::where('school_year_id', $sy->id)->where('name', $name)->exists() ? $name.' '.$n : $name;
                    $mine->push(Section::create(['name' => $label, 'school_year_id' => $sy->id, 'grade_level_id' => $g->id, 'capacity' => 40]));
                }
                foreach ($mine->take(4)->values() as $k => $s) {
                    $s->update(['room' => Timetable::roomName($g, $k + 1), 'capacity' => max($s->capacity, 40)]);
                }
            }
            $this->info('Sections and rooms ready.');

            // 2. Students: top up to $per per section (demo students only)
            $next = (int) Student::where('lrn', 'like', '14000%')->max(DB::raw('cast(substr(lrn, 2) as integer)')) - 40000000000 + 1;
            $next = max($next, 0);
            $made = 0;
            foreach ($levels as $g) {
                $want = 4 * $per;
                $have = Enrollment::where('school_year_id', $sy->id)->where('grade_level_id', $g->id)->where('status', 'enrolled')->count();
                for ($i = $have; $i < $want; $i++, $next++) {
                    [$f, $m, $l] = DemoNames::for($next);
                    $st = Student::create(['lrn' => '1'.str_pad((string) (40000000000 + $next), 11, '0', STR_PAD_LEFT), 'first_name' => $f, 'middle_name' => $m, 'last_name' => $l,
                        'sex' => $next % 2 ? 'Female' : 'Male', 'birthdate' => now()->subYears(6 + Timetable::gradeNumber($g))->subDays($next % 300), 'address' => ($next % 200 + 1).' Mabuhay St., Quezon City']);
                    $e = Enrollment::create(['student_id' => $st->id, 'school_year_id' => $sy->id, 'grade_level_id' => $g->id, 'enrolled_on' => $sy->starts_on, 'status' => 'enrolled']);
                    Billing::assess($e);
                    $made++;
                }
            }
            $this->info("Added $made demo students.");

            // 3. Subject teachers: enough per subject for a ~24 h/week load
            $sections = Section::where('school_year_id', $sy->id)->whereIn('grade_level_id', $levels->pluck('id'))->with('gradeLevel')->get();
            foreach ($sections as $s) {
                foreach ($s->gradeLevel->subjects()->where('is_active', true)->get() as $sub) {
                    ClassSubject::firstOrCreate(['section_id' => $s->id, 'subject_id' => $sub->id]);
                }
            }
            $classes = ClassSubject::with('subject', 'section.gradeLevel')->whereIn('section_id', $sections->pluck('id'))->get();
            $bySubject = $classes->groupBy(fn ($c) => $c->subject->name);
            $pool = User::where('role', Role::TEACHER)->where('is_active', true)->orderBy('id')->get();
            $k = 0;
            $teachersFor = [];
            foreach ($bySubject as $subject => $list) {
                $hours = $list->sum(fn ($c) => Timetable::hoursFor($c));
                $need = (int) ceil($hours / self::TEACHER_LOAD) + 1;
                $teachersFor[$subject] = [];
                for ($i = 0; $i < $need; $i++) {
                    $t = $pool->get($k++) ?? $this->newTeacher($k);
                    Employee::where('user_id', $t->id)->update(['position' => 'Teacher I – '.$subject]);
                    $teachersFor[$subject][] = $t->id;
                }
            }
            $load = [];
            ClassSubject::withoutEvents(function () use ($bySubject, $teachersFor, &$load) {
                foreach ($bySubject as $subject => $list) {
                    foreach ($list->sortBy(fn ($c) => [$c->section->gradeLevel->sort_order, $c->section->name]) as $c) {
                        $t = collect($teachersFor[$subject])->sortBy(fn ($id) => $load[$id] ?? 0)->first();
                        $load[$t] = ($load[$t] ?? 0) + Timetable::hoursFor($c);
                        ClassSubject::whereKey($c->id)->update(['teacher_id' => $t]);
                    }
                }
            });
            // one adviser per section, different people
            $advisers = collect($load)->keys()->values();
            foreach ($sections->values() as $i => $s) {
                $s->update(['adviser_id' => $advisers[$i % $advisers->count()]]);
            }
            $this->info('Assigned '.count($load).' subject teachers.');

            // 4. Put every enrolled student into a section (balanced by sex)
            foreach ($levels as $g) {
                $secs = Section::where('school_year_id', $sy->id)->where('grade_level_id', $g->id)->pluck('capacity', 'id')->map(fn ($c) => (int) $c)->all();
                $ens = Enrollment::with('student.guardians')->where('school_year_id', $sy->id)->where('grade_level_id', $g->id)->where('status', 'enrolled')->get();
                $students = $ens->mapWithKeys(fn ($e) => [$e->id => ['sex' => $e->student->sex, 'ga' => $e->general_average, 'family' => null]])->all();
                $r = Allocator::randomize($students, $secs, [], ['gender' => true], 2027 + $g->id);
                foreach ($ens as $e) {
                    if (isset($r['plan'][$e->id]) && $e->section_id !== $r['plan'][$e->id]) {
                        Enrollment::whereKey($e->id)->update(['section_id' => $r['plan'][$e->id]]);
                    }
                }
            }
            $this->info('Students placed in sections.');
        });

        [$placed, $failed] = Timetable::schedule($sy, true);
        AuditLog::record('demo_realistic', null, [], ['classes' => $placed, 'failed' => count($failed)], 'Realistic demo data built for '.$sy->name);
        $this->info("Timetable: $placed classes scheduled.");
        foreach ($failed as $f) {
            $this->warn('Could not place: '.$f);
        }

        return $this->call('timetable:check', ['--limit' => 10]);
    }

    private function newTeacher(int $k): User
    {
        for ($n = 3001 + $k * 2; ; $n += 2) {
            [$f, , $l] = DemoNames::for($n);
            if (! User::where('name', "$f $l")->exists()) {
                break;
            }
        }
        $i = 200 + $k;
        while (User::where('email', "teacher$i@school.test")->exists()) {
            $i++;
        }
        $u = User::create(['name' => "$f $l", 'email' => "teacher$i@school.test", 'password' => Hash::make('password'), 'role' => Role::TEACHER, 'is_active' => true]);
        Employee::firstOrCreate(['user_id' => $u->id], ['employee_no' => 'EMP-'.str_pad((string) (100 + $u->id), 3, '0', STR_PAD_LEFT), 'first_name' => $f, 'last_name' => $l,
            'position' => 'Teacher I', 'department' => 'Academics', 'monthly_salary' => 27000, 'date_hired' => now()->subYears(1 + $k % 10)]);

        return $u;
    }
}
