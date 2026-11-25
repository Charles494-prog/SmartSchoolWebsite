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
        $mk('Bookstore Office', 'bookstore@school.test', Role::BOOKSTORE);
        $teachers = collect([
            ['Ana Villanueva', 'teacher@school.test'], ['Mark Bautista', 'teacher2@school.test'], ['Grace Mendoza', 'teacher3@school.test'], ['Paolo Navarro', 'teacher4@school.test'],
            ['Liza Soriano', 'teacher5@school.test'], ['Ramon Dizon', 'teacher6@school.test'], ['Joy Pascual', 'teacher7@school.test'], ['Edwin Salazar', 'teacher8@school.test'],
            ['Cecilia Manalo', 'teacher9@school.test'], ['Arnel Ocampo', 'teacher10@school.test'], ['Rhea Bernardo', 'teacher11@school.test'], ['Victor Lim', 'teacher12@school.test'],
        ])->map(fn ($t) => $mk($t[0], $t[1], Role::TEACHER));
        foreach ($teachers as $i => $t) {
            Employee::firstOrCreate(['employee_no' => 'EMP-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT)], ['user_id' => $t->id, 'first_name' => explode(' ', $t->name)[0], 'last_name' => explode(' ', $t->name)[1], 'position' => 'Teacher I', 'department' => 'Academics', 'monthly_salary' => 27000 + $i * 1500, 'date_hired' => now()->subYears($i + 1)]);
        }

        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Angel', 'Paolo', 'Jasmine', 'Carlo', 'Bea', 'Miguel', 'Sofia', 'Rafael', 'Andrea', 'Luis', 'Nicole', 'Gabriel', 'Patricia', 'Daniel', 'Kristine'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Gonzales', 'Lopez', 'Castillo', 'Villanueva', 'Rivera', 'Aquino'];

        foreach (range(101, 106) as $no) {
            Room::firstOrCreate(['name' => 'Room '.$no], ['building' => 'Main building', 'capacity' => $no <= 104 ? 40 : 30]);
        }
        // Two sections in every grade level (Grade 1 – 12) so the overall awards have lots to show.
        $plan = [['Grade 7', 'Rizal', 0], ['Grade 7', 'Bonifacio', 1], ['Grade 8', 'Mabini', 2]];
        if (! app()->runningUnitTests()) {
            $plan[] = ['Grade 8', 'Luna', 3];
        }
        $names = ['Sampaguita', 'Ilang-Ilang', 'Rosal', 'Camia', 'Dama de Noche', 'Gumamela', 'Orchid', 'Jasmine', 'Narra', 'Molave', 'Acacia', 'Mahogany',
            'Diamond', 'Emerald', 'Ruby', 'Sapphire', 'Jade', 'Pearl', 'Aguinaldo', 'Del Pilar', 'Jacinto', 'Silang'];
        $ni = 0;
        foreach (app()->runningUnitTests() ? [] : [1, 2, 3, 4, 5, 6, 9, 10, 11, 12] as $g) {   // tests stay fast
            foreach ([0, 1] as $k) {
                $plan[] = ['Grade '.$g, $names[$ni++ % count($names)], ($g + $k) % 12];
            }
        }
        $n = 0;
        $secIndex = 0;
        $busy = [];
        foreach ($plan as [$levelName, $secName, $adv]) {
            $level = GradeLevel::where('name', $levelName)->first();
            $roomName = 'Room '.(101 + $secIndex);   // one homeroom per section – no two sections share a room
            Room::firstOrCreate(['name' => $roomName], ['building' => 'Main building', 'capacity' => 40]);
            $section = Section::firstOrCreate(['name' => $secName, 'school_year_id' => $sy->id], ['grade_level_id' => $level->id, 'adviser_id' => $teachers[$adv]->id, 'room' => $roomName, 'capacity' => 40]);
            $times = [['07:30', '08:30'], ['08:30', '09:30'], ['09:45', '10:45'], ['10:45', '11:45'], ['13:00', '14:00'], ['14:00', '15:00'], ['15:00', '16:00'], ['16:00', '17:00']];
            // Give each subject a time and a teacher who is free then (no teacher teaches two classes at once).
            foreach ($level->subjects as $i => $sub) {
                $cs = ClassSubject::where(['section_id' => $section->id, 'subject_id' => $sub->id])->first();
                if (! $cs) {
                    $slot = ($i + $secIndex) % 8;
                    $t = collect(range(0, count($teachers) - 1))->map(fn ($k) => ($i + $secIndex * 5 + $k) % count($teachers))->first(fn ($k) => empty($busy[$k][$slot])) ?? 0;
                    $busy[$t][$slot] = true;
                    $cs = ClassSubject::create(['section_id' => $section->id, 'subject_id' => $sub->id, 'teacher_id' => $teachers[$t]->id]);
                    foreach ([1, 2, 3, 4, 5] as $d) {
                        $cs->slots()->create(['day_of_week' => $d, 'starts_at' => $times[$slot][0], 'ends_at' => $times[$slot][1], 'room' => $section->room]);
                    }
                }
            }
            $secIndex++;
            for ($k = 0; $k < 12; $k++, $n++) {
                $sex = $n % 2 ? 'Female' : 'Male';
                $st = Student::firstOrCreate(['lrn' => '1'.str_pad((string) (40000000000 + $n), 11, '0', STR_PAD_LEFT)], [
                    'first_name' => $first[($n < 36 ? $n : $n * 7 + 3) % count($first)], 'middle_name' => $last[($n + 3) % count($last)], 'last_name' => $last[($n < 36 ? $n : $n * 11 + 5) % count($last)],
                    'sex' => $sex, 'birthdate' => Carbon::create(2013 - ($level->sort_order - 7), ($n % 12) + 1, ($n % 27) + 1),
                    'address' => ($n + 10).' Mabuhay St., Quezon City', 'religion' => 'Roman Catholic',
                ]);
                Enrollment::firstOrCreate(['student_id' => $st->id, 'school_year_id' => $sy->id], ['grade_level_id' => $level->id, 'section_id' => $section->id, 'enrolled_on' => $sy->starts_on]);
                $email = $n === 0 ? 'parent@school.test' : 'parent'.$n.'@school.test';
                $pu = $mk('Parent of '.$st->first_name.' '.$st->last_name, $email, Role::PARENT);
                $g = Guardian::firstOrCreate(['user_id' => $pu->id], ['name' => $pu->name, 'phone' => '0917'.str_pad((string) (1000000 + $n), 7, '0'), 'email' => $email]);
                $st->guardians()->syncWithoutDetaching([$g->id => ['relationship' => $n % 3 ? 'Mother' : 'Father', 'is_primary' => true, 'is_emergency_contact' => true]]);
                if ($k === 0 || $k === 1) {
                    // A couple of student logins per section (student@school.test is the first one).
                    $su = $mk($st->first_name.' '.$st->last_name, $n === 0 ? 'student@school.test' : 'student'.$n.'@school.test', Role::STUDENT);
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
                    $ability = $idx === 5 ? 0.45 : ($idx < 3 ? 0.92 + $idx * 0.025 : 0.62 + (($s->id * 7 + $cs->id) % 35) / 100); // a few honor students per section
                    Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => round(min($max, $max * $ability + mt_rand(-2, 2)), 0)]);
                }
            }
            $cs->submissions()->create(['term' => 1, 'status' => 'approved', 'submitted_by' => $cs->teacher_id]);
        }

        // Class cards (Online mode): announcements, documents and classwork. Graded classwork counts toward Q2.
        $posts = [
            ['announcement', 'Welcome to class!', "Hi everyone! This is where I'll post announcements, lessons and your work. Check it every day.", null, null, null],
            ['material', 'Lesson 3 notes', "Read these notes before our next meeting.", null, null, null],
            ['assignment', 'Reading log – Week 3', "Read the assigned chapter and write 5 sentences about the main idea.", -6, 20, 'ww'],
            ['activity', 'Group activity: concept map', "With your group, make a concept map of today's lesson. Upload a photo.", -1, 30, 'pt'],
            ['assignment', 'Practice worksheet', "Practice only – this is not included in your grade.", 3, 25, null],
            ['quiz', 'Quiz 2', "Short quiz on lessons 1–3.", 2, 20, 'ww'],
            ['project', 'Quarter project', "Make a poster or short video about a real-life use of this subject.", 14, 100, 'pt'],
        ];
        $design = ['<div class="hero"><h2>Let\'s learn!</h2><p>Tap the star ⭐</p><button id="star">⭐</button><p id="msg"></p></div>',
            '.hero{padding:24px;border-radius:16px;text-align:center;background:linear-gradient(135deg,#eef2ff,#ecfeff);animation:rise .8s ease-out}h2{margin:0 0 6px}#star{font-size:40px;border:0;background:none;cursor:pointer;transition:transform .2s}#star:hover{transform:scale(1.3) rotate(15deg)}@keyframes rise{from{opacity:0;transform:translateY(20px)}}',
            'document.getElementById("star").onclick=()=>{document.getElementById("msg").textContent="Great job! 🎉"}'];
        foreach (ClassSubject::whereHas('section', fn ($q) => $q->where('school_year_id', $sy->id)->whereIn('name', ['Rizal', 'Bonifacio', 'Mabini', 'Luna']))->with('subject')->get() as $cs) {
            if ($cs->assignments()->exists()) {
                continue;
            }
            $students = $cs->section->students()->get();
            foreach ($posts as $pi => [$type, $title, $text, $days, $max, $component]) {
                $a = Assignment::create(['class_subject_id' => $cs->id, 'type' => $type, 'title' => $title, 'instructions' => $text,
                    'due_at' => $days === null ? null : today()->addDays($days)->setTime(17, 0), 'max_score' => $max, 'component' => $component, 'term' => $component ? 2 : null,
                    'body_html' => $pi === 0 ? '<style>'.$design[1].'</style>'.$design[0].'<script>'.$design[2].'</script>' : null,
                    'created_at' => now()->subDays(10 - $pi)]);
                if ($component) {
                    $as = Assessment::create(['class_subject_id' => $cs->id, 'term' => 2, 'component' => $component, 'title' => $title, 'max_score' => $max, 'given_on' => $a->due_at->toDateString()]);
                    $a->update(['assessment_id' => $as->id]);
                }
                if ($days === null) {
                    continue;
                }
                foreach ($students as $si => $st) {
                    $turnIn = $days < 0 ? ($si * 7 + $cs->id) % 10 < 8 : ($days < 7 ? ($si + $cs->id) % 3 === 0 : false);
                    if (! $turnIn) {
                        continue;
                    }
                    $graded = $days < -3 && $si % 4 !== 0;
                    $score = $graded ? round($max * (0.7 + (($si * 13 + $pi) % 30) / 100)) : null;
                    $a->submissions()->create(['student_id' => $st->id, 'body' => 'Here is my work for '.$title.'.', 'submitted_at' => $a->due_at->copy()->subHours(($si % 5) * 6 - 4), 'score' => $score, 'feedback' => $graded ? 'Good job! Keep it up.' : null]);
                    if ($graded && $a->assessment_id) {
                        Score::create(['assessment_id' => $a->assessment_id, 'student_id' => $st->id, 'score' => $score]);
                    }
                }
            }
        }

        // Quiz questions (GIFT – the same text a teacher can paste or import from Moodle)
        $gift = "::Capital:: What is the capital of the Philippines? { =Manila ~Cebu City ~Davao City ~Baguio }\n\n"
            ."The Philippines has more than 7,000 islands. {T}\n\n"
            ."Which of these are primary colors? { ~%50%Red ~%50%Blue ~%-50%Green ~%-50%Orange }\n\n"
            ."What is 12 × 12? {#144}\n\n"
            ."Who wrote Noli Me Tangere? { =Jose Rizal =Rizal =Dr. Jose Rizal }\n\n"
            ."Match each animal to where it lives. { =Fish -> Water =Bird -> Nest =Carabao -> Farm }\n\n"
            ."In 2–3 sentences, explain why reading every day helps you. {}";
        foreach (Assignment::where('type', 'quiz')->whereDoesntHave('questions')->get() as $qz) {
            foreach (\App\Services\Quiz::parseGift($gift) as $i => $q) {
                $qz->questions()->create($q + ['position' => $i, 'points' => $q['type'] === 'essay' ? 3 : 1]);
            }
            $qz->update(['time_limit' => 15, 'attempts_allowed' => 2, 'shuffle_answers' => true]);
        }

        // Special awards and a few given / suggested ones
        if (! \App\Models\Award::exists()) {
            foreach (\App\Models\Award::DEFAULTS as [$name, $desc]) {
                \App\Models\Award::create(['name' => $name, 'description' => $desc]);
            }
        }
        if (\App\Models\AwardNomination::doesntExist()) {
            foreach (Section::where('school_year_id', $sy->id)->get() as $si => $sec) {
                $kids = $sec->students()->get();
                if ($kids->count() < 5) {
                    continue;
                }
                \App\Models\AwardNomination::create(['award_id' => \App\Models\Award::where('name', 'Best in Conduct')->value('id'), 'student_id' => $kids[3]->id, 'school_year_id' => $sy->id, 'term' => 1, 'reason' => 'Always respectful and kind to classmates.', 'status' => 'approved', 'nominated_by' => $sec->adviser_id]);
                \App\Models\AwardNomination::create(['award_id' => \App\Models\Award::where('name', 'Leadership Award')->value('id'), 'student_id' => $kids[4]->id, 'school_year_id' => $sy->id, 'term' => 1, 'reason' => 'Led the class clean-up drive.', 'status' => 'pending', 'nominated_by' => $sec->adviser_id]);
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

        // Perfect attendance: a few students per section came every school day since classes started.
        foreach (Section::where('school_year_id', $sy->id)->get() as $sec) {
            $kids = $sec->students()->get();
            if ($kids->count() < 8) {
                continue;
            }
            foreach ([$kids[1], $kids[6], $kids[7]] as $st) {
                for ($d = $sy->starts_on->copy(); $d < today(); $d->addDay()) {
                    if ($d->isWeekend()) {
                        continue;
                    }
                    AttendanceRecord::updateOrCreate(['student_id' => $st->id, 'date' => $d->toDateString(), 'class_subject_id' => null], ['section_id' => $sec->id, 'status' => 'present', 'time_in' => '07:05:00', 'method' => 'qr']);
                }
            }
        }

        if (! \App\Models\SchoolService::exists()) {
            foreach (\App\Models\SchoolService::DEFAULTS as [$nm, $desc, $fee, $days]) {
                \App\Models\SchoolService::create(['name' => $nm, 'description' => $desc, 'fee' => $fee, 'days' => $days]);
            }
        }
        // Bookstore
        foreach ([['PE uniform (shirt + jogging pants)', 'uniform', 650, 'XS, S, M, L, XL', 40], ['School uniform – blouse', 'uniform', 450, 'XS, S, M, L', 30], ['School uniform – polo', 'uniform', 450, 'XS, S, M, L, XL', 30],
            ['School ID lace', 'merch', 60, null, 200], ['School tote bag', 'merch', 250, null, 50], ['Foundation Day shirt', 'souvenir', 300, 'S, M, L, XL', 60],
            ['Science Links 7 (textbook)', 'book', 520, null, 25], ['Grammar workbook', 'book', 280, null, null], ['Yearbook 2027 (pre-order)', 'souvenir', 1200, null, null]] as [$nm, $cat, $price, $sizes, $stock]) {
            \App\Models\ShopItem::firstOrCreate(['name' => $nm], ['category' => $cat, 'price' => $price, 'sizes' => $sizes ? array_map('trim', explode(',', $sizes)) : null, 'stock' => $stock, 'description' => null]);
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
