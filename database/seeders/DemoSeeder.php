<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Application;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Book;
use App\Models\ClassSubject;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\FeeItem;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/** Sample school so every screen has something to show. Password for all demo accounts: Demo12345 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        mt_srand(42);
        // Keep a school's real name/address if it already set one.
        foreach (['school_name' => 'Sampaguita Integrated School', 'school_address' => 'Rizal St., Brgy. San Isidro, Quezon City', 'school_head' => 'Dr. Maria Santos'] as $k => $v) {
            if (! Setting::get($k)) {
                Setting::put($k, $v);
            }
        }

        $start = Carbon::create(now()->month >= 6 ? now()->year : now()->year - 1, 6, 16);
        $sy = SchoolYear::firstOrCreate(['name' => $start->year.'-'.($start->year + 1)], ['starts_on' => $start, 'ends_on' => $start->copy()->addMonths(10), 'is_current' => true]);
        SchoolYear::where('id', '!=', $sy->id)->update(['is_current' => false]);

        $pw = 'Demo12345';
        $mk = fn ($name, $email, $role) => User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $pw, 'role' => $role]);
        User::where('email', 'admin@school.test')->update(['must_change_password' => false]);
        $mk('Rosa Dela Cruz', 'registrar@school.test', Role::REGISTRAR);
        $mk('Ben Ramos', 'cashier@school.test', Role::CASHIER);
        $mk('Lito Garcia', 'librarian@school.test', Role::LIBRARIAN);
        $mk('Nurse Joy Reyes', 'nurse@school.test', Role::NURSE);
        $mk('Carmen Aquino', 'guidance@school.test', Role::GUIDANCE);
        $mk('Henry Cruz', 'hr@school.test', Role::HR);
        $mk('Mang Jun Dela Rosa', 'guard@school.test', Role::GUARD);
        $teachers = collect([
            ['Ana Villanueva', 'teacher@school.test'], ['Mark Bautista', 'teacher2@school.test'], ['Grace Mendoza', 'teacher3@school.test'], ['Paolo Navarro', 'teacher4@school.test'],
        ])->map(fn ($t) => $mk($t[0], $t[1], Role::TEACHER));
        foreach ($teachers as $i => $t) {
            Employee::firstOrCreate(['employee_no' => 'EMP-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)], ['user_id' => $t->id, 'first_name' => explode(' ', $t->name)[0], 'last_name' => explode(' ', $t->name)[1], 'position' => 'Teacher I', 'department' => 'Academics', 'monthly_salary' => 27000 + $i * 1500, 'date_hired' => now()->subYears($i + 1)]);
        }

        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Angel', 'Paolo', 'Jasmine', 'Carlo', 'Bea', 'Miguel', 'Sofia', 'Rafael', 'Andrea', 'Luis', 'Nicole', 'Gabriel', 'Patricia', 'Daniel', 'Kristine'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Gonzales', 'Lopez', 'Castillo', 'Villanueva', 'Rivera', 'Aquino'];

        foreach (range(101, 106) as $no) {
            Room::firstOrCreate(['name' => 'Room '.$no], ['building' => 'Main building', 'capacity' => $no <= 104 ? 40 : 30]);
        }
        $plan = [['Grade 7', 'Rizal', 0], ['Grade 7', 'Bonifacio', 1], ['Grade 8', 'Mabini', 2]];
        $n = 0;
        foreach ($plan as [$levelName, $secName, $adv]) {
            $level = GradeLevel::where('name', $levelName)->first();
            $section = Section::firstOrCreate(['name' => $secName, 'school_year_id' => $sy->id], ['grade_level_id' => $level->id, 'adviser_id' => $teachers[$adv]->id, 'room' => 'Room '.(101 + $adv), 'capacity' => 40]);
            $times = [['07:30', '08:30'], ['08:30', '09:30'], ['09:45', '10:45'], ['10:45', '11:45'], ['13:00', '14:00'], ['14:00', '15:00'], ['15:00', '16:00'], ['16:00', '17:00']];
            foreach ($level->subjects as $i => $sub) {
                $cs = ClassSubject::firstOrCreate(['section_id' => $section->id, 'subject_id' => $sub->id], ['teacher_id' => $teachers[($i + $adv) % 4]->id]);
                if ($cs->slots()->doesntExist()) {
                    foreach ([1, 2, 3, 4, 5] as $d) {
                        $cs->slots()->create(['day_of_week' => $d, 'starts_at' => $times[$i % 8][0], 'ends_at' => $times[$i % 8][1], 'room' => $section->room]);
                    }
                }
            }
            for ($k = 0; $k < 12; $k++, $n++) {
                $sex = $n % 2 ? 'Female' : 'Male';
                $st = Student::firstOrCreate(['lrn' => '1'.str_pad((string) (40000000000 + $n), 11, '0', STR_PAD_LEFT)], [
                    'first_name' => $first[$n % count($first)], 'middle_name' => $last[($n + 3) % count($last)], 'last_name' => $last[$n % count($last)],
                    'sex' => $sex, 'birthdate' => Carbon::create(2013 - ($level->sort_order - 7), ($n % 12) + 1, ($n % 27) + 1),
                    'address' => ($n + 10).' Mabuhay St., Quezon City', 'religion' => 'Roman Catholic',
                ]);
                Enrollment::firstOrCreate(['student_id' => $st->id, 'school_year_id' => $sy->id], ['grade_level_id' => $level->id, 'section_id' => $section->id, 'enrolled_on' => $sy->starts_on]);
                $email = $n === 0 ? 'parent@school.test' : 'parent'.$n.'@school.test';
                $pu = $mk('Parent of '.$st->first_name.' '.$st->last_name, $email, Role::PARENT);
                $g = Guardian::firstOrCreate(['user_id' => $pu->id], ['name' => $pu->name, 'phone' => '0917'.str_pad((string) (1000000 + $n), 7, '0'), 'email' => $email]);
                $st->guardians()->syncWithoutDetaching([$g->id => ['relationship' => $n % 3 ? 'Mother' : 'Father', 'is_primary' => true, 'is_emergency_contact' => true]]);
                if ($n === 0) {
                    $su = $mk($st->first_name.' '.$st->last_name, 'student@school.test', Role::STUDENT);
                    $st->update(['user_id' => $su->id]);
                }
            }
        }

        $firstStudent = Student::orderBy('id')->first();
        $firstStudent->transfers()->firstOrCreate(['direction' => 'in'], ['school_name' => 'San Roque Elementary School', 'transfer_date' => $sy->starts_on]);

        // Grades for Q1 (and part of Q2) with a few weak students
        foreach (ClassSubject::whereHas('section', fn ($q) => $q->where('school_year_id', $sy->id))->get() as $cs) {
            if ($cs->assessments()->exists()) {
                continue;
            }
            $items = [[1, 'ww', 'Quiz 1', 20], [1, 'ww', 'Quiz 2', 25], [1, 'pt', 'Project', 50], [1, 'pt', 'Oral recitation', 30], [1, 'qa', 'Quarterly exam', 50], [2, 'ww', 'Quiz 1', 20]];
            $students = $cs->section->students()->get();
            foreach ($items as [$term, $comp, $title, $max]) {
                $a = Assessment::create(['class_subject_id' => $cs->id, 'term' => $term, 'component' => $comp, 'title' => $title, 'max_score' => $max, 'given_on' => $sy->starts_on->copy()->addWeeks(3 * $term)]);
                foreach ($students as $idx => $s) {
                    $ability = $idx === 5 ? 0.45 : 0.62 + (($s->id * 7 + $cs->id) % 35) / 100;
                    Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => round(min($max, $max * $ability + mt_rand(-2, 2)), 0)]);
                }
            }
            $cs->submissions()->create(['term' => 1, 'status' => 'approved', 'submitted_by' => $cs->teacher_id]);
        }

        // Class cards (Online mode): assignments, projects and activities with some student work
        $posts = [
            ['assignment', 'Reading log – Week 3', "Read the assigned chapter and write 5 sentences about the main idea.", -6, 20],
            ['activity', 'Group activity: concept map', "With your group, make a concept map of today's lesson. Upload a photo.", -1, 30],
            ['assignment', 'Worksheet 4', "Answer items 1–15. Show your solution.", 3, 25],
            ['project', 'Quarter project', "Make a poster or short video about a real-life use of this subject. Rubric attached in class.", 14, 100],
        ];
        foreach (ClassSubject::whereHas('section', fn ($q) => $q->where('school_year_id', $sy->id))->with('subject')->get() as $cs) {
            if ($cs->assignments()->exists()) {
                continue;
            }
            $students = $cs->section->students()->get();
            foreach ($posts as $pi => [$type, $title, $text, $days, $max]) {
                $a = Assignment::create(['class_subject_id' => $cs->id, 'type' => $type, 'title' => $cs->subject->name.': '.$title, 'instructions' => $text, 'due_at' => today()->addDays($days)->setTime(17, 0), 'max_score' => $max]);
                foreach ($students as $si => $st) {
                    $turnIn = $days < 0 ? ($si * 7 + $cs->id) % 10 < 8 : ($days < 7 ? ($si + $cs->id) % 3 === 0 : false);
                    if (! $turnIn) {
                        continue;
                    }
                    $graded = $days < -3 && $si % 4 !== 0;
                    $a->submissions()->create(['student_id' => $st->id, 'body' => 'Here is my work for '.$title.'.', 'submitted_at' => $a->due_at->copy()->subHours(($si % 5) * 6 - 4),
                        'score' => $graded ? round($max * (0.7 + (($si * 13 + $pi) % 30) / 100)) : null, 'feedback' => $graded ? 'Good job! Keep it up.' : null]);
                }
            }
        }

        // Attendance for the last 10 school days
        $sections = Section::where('school_year_id', $sy->id)->get();
        for ($d = today()->subDays(14); $d < today(); $d->addDay()) {
            if ($d->isWeekend()) {
                continue;
            }
            foreach ($sections as $sec) {
                foreach ($sec->students()->get() as $i => $s) {
                    $roll = ($s->id * 31 + $d->day) % 20;
                    $status = $roll === 0 ? 'absent' : ($roll < 3 ? 'late' : 'present');
                    AttendanceRecord::firstOrCreate(['student_id' => $s->id, 'date' => $d->toDateString(), 'class_subject_id' => null], ['section_id' => $sec->id, 'status' => $status, 'time_in' => $status === 'absent' ? null : ($status === 'late' ? '07:45:00' : '07:10:00'), 'method' => 'qr']);
                }
            }
        }

        // Finance
        foreach (GradeLevel::whereIn('department', ['jhs'])->get() as $gl) {
            FeeItem::firstOrCreate(['school_year_id' => $sy->id, 'grade_level_id' => $gl->id, 'name' => 'Tuition fee'], ['category' => 'tuition', 'amount' => 25000]);
        }
        FeeItem::firstOrCreate(['school_year_id' => $sy->id, 'grade_level_id' => null, 'name' => 'Miscellaneous fee'], ['category' => 'miscellaneous', 'amount' => 4500]);
        FeeItem::firstOrCreate(['school_year_id' => $sy->id, 'grade_level_id' => null, 'name' => 'ID & handbook'], ['category' => 'other', 'amount' => 350]);
        $sib = Discount::firstOrCreate(['name' => 'Sibling discount'], ['type' => 'percent', 'value' => 10, 'tuition_only' => true]);
        Discount::firstOrCreate(['name' => 'Academic scholarship'], ['type' => 'percent', 'value' => 50, 'tuition_only' => true, 'is_scholarship' => true]);
        $cashier = User::where('role', Role::CASHIER)->first();
        foreach (Enrollment::where('school_year_id', $sy->id)->get() as $i => $e) {
            if ($e->account) {
                continue;
            }
            $acc = Billing::assess($e, ['full', 'quarterly', 'monthly'][$i % 3], $i % 7 === 0 ? [$sib->id] : []);
            $paid = $i % 4 === 3 ? 0 : min($acc->net_amount, $acc->installments->first()->amount);
            if ($paid > 0) {
                $acc->payments()->create(['or_number' => Billing::nextOrNumber(), 'amount' => $paid, 'method' => 'cash', 'paid_on' => $sy->starts_on, 'received_by' => $cashier->id]);
            }
        }

        // Communication & services
        $admin = User::where('role', Role::ADMIN)->first();
        Announcement::firstOrCreate(['title' => 'Welcome to the new school year!'], ['body' => "Classes start Monday. Please bring your QR ID card every day for attendance.\nParents can view grades, attendance and balances in the portal.", 'audience' => 'all', 'author_id' => $admin->id, 'is_pinned' => true, 'published_at' => now()->subDays(5)]);
        Announcement::firstOrCreate(['title' => 'First quarter exams'], ['body' => 'First quarter examinations are scheduled next month. Review materials are available from your teachers.', 'audience' => 'students', 'author_id' => $admin->id, 'published_at' => now()->subDays(2)]);
        Event::firstOrCreate(['title' => 'Parent-Teacher Conference'], ['type' => 'meeting', 'starts_on' => today()->addDays(10)]);
        Event::firstOrCreate(['title' => 'First Quarter Examinations'], ['type' => 'exam', 'starts_on' => today()->addDays(20), 'ends_on' => today()->addDays(22)]);
        Event::firstOrCreate(['title' => 'National Heroes Day'], ['type' => 'holiday', 'starts_on' => today()->addDays(5)]);
        foreach ([['Noli Me Tangere', 'Jose Rizal', 'Filipino Literature', 3], ['El Filibusterismo', 'Jose Rizal', 'Filipino Literature', 3], ['Science Links 7', 'Rex Bookstore', 'Textbook', 10], ['Philippine History', 'Teodoro Agoncillo', 'History', 2]] as [$t, $a, $c, $copies]) {
            Book::firstOrCreate(['title' => $t], ['author' => $a, 'category' => $c, 'copies' => $copies, 'shelf' => 'A'.mt_rand(1, 9)]);
        }
        Application::firstOrCreate(['reference_no' => 'APP-DEMO01'], ['school_year_id' => $sy->id, 'grade_level_id' => GradeLevel::where('name', 'Grade 7')->value('id'), 'applicant_type' => 'transferee', 'first_name' => 'Isabel', 'last_name' => 'Fernandez', 'sex' => 'Female', 'birthdate' => '2013-04-12', 'address' => '22 Kalayaan Ave., Quezon City', 'previous_school' => 'Sto. Niño Elementary School', 'guardian_name' => 'Liza Fernandez', 'guardian_relationship' => 'Mother', 'guardian_phone' => '09181234567', 'guardian_email' => 'liza.fernandez@example.com', 'consent_at' => now()]);

        $this->command?->info('Demo data ready. All demo passwords: Demo12345 (admin: admin@school.test / Admin12345)');
    }
}
