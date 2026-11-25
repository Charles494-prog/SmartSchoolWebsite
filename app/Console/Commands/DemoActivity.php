<?php

namespace App\Console\Commands;

use App\Models\AccountCharge;
use App\Models\Application;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ClassSubject;
use App\Models\Employee;
use App\Models\GradeLevel;
use App\Models\GradeSubmission;
use App\Models\Payroll;
use App\Models\SchoolService;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\ServiceRequest;
use App\Models\ShopItem;
use App\Models\ShopOrder;
use App\Models\Student;
use App\Models\User;
use App\Services\Billing;
use App\Services\Payroll as Calc;
use App\Support\DemoNames;
use App\Support\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Things to test with, for the current school year: Q1 grades (most approved, some submitted / returned / in progress),
 * applications waiting for review, bookstore orders in every status, document requests in every status,
 * an employee record for every teacher, and a draft payroll.
 */
class DemoActivity extends Command
{
    protected $signature = 'demo:activity {--force : Skip the confirmation}';

    protected $description = 'Add demo grades, applications, bookstore orders, document requests and payroll to test with';

    public function handle(): int
    {
        $sy = SchoolYear::current();
        if (! $sy || ! $this->option('force') && ! $this->confirm("Add demo activity to {$sy->name}?")) {
            return self::FAILURE;
        }
        mt_srand(2027);
        DB::transaction(function () use ($sy) {
            $this->grades($sy);
            $this->applications($sy);
            $this->orders($sy);
            $this->requests($sy);
            $this->payroll();
        });
        AuditLog::record('demo_activity', null, [], [], 'Demo activity added for '.$sy->name);
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function grades(SchoolYear $sy): void
    {
        $items = [[1, 'ww', 'Quiz 1', 20], [1, 'ww', 'Quiz 2', 25], [1, 'pt', 'Project', 50], [1, 'pt', 'Oral recitation', 30], [1, 'qa', 'Quarterly exam', 50], [2, 'ww', 'Quiz 1', 20]];
        $classes = ClassSubject::with('section')->whereHas('section', fn ($q) => $q->where('school_year_id', $sy->id))->whereDoesntHave('assessments')->orderBy('id')->get();
        $n = 0;
        foreach ($classes as $ci => $cs) {
            $students = $cs->section->students()->get();
            $scores = [];
            foreach ($items as $ii => [$term, $comp, $title, $max]) {
                $a = Assessment::create(['class_subject_id' => $cs->id, 'term' => $term, 'component' => $comp, 'title' => $title, 'max_score' => $max, 'given_on' => $sy->starts_on->copy()->addWeeks(3 * $term + $ii)]);
                foreach ($students as $idx => $s) {
                    if ($ci % 9 === 4 && $idx % 11 === 3 && $term === 1 && $comp === 'qa') {
                        continue;   // a few missing scores to find in the review screen
                    }
                    $ability = $idx < 3 ? 0.9 + $idx * 0.03 : 0.6 + (($s->id * 7 + $cs->id) % 36) / 100;
                    $scores[] = ['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => max(0, round(min($max, $max * $ability + mt_rand(-2, 2)))), 'created_at' => now(), 'updated_at' => now()];
                }
            }
            foreach (array_chunk($scores, 500) as $chunk) {
                Score::insert($chunk);
            }
            // Q1 status mix for the approval queue
            $status = match ($ci % 10) {
                0, 1 => 'submitted', 2 => 'returned', 3 => null, default => 'approved'
            };
            if ($status) {
                GradeSubmission::create(['class_subject_id' => $cs->id, 'term' => 1, 'status' => $status, 'submitted_by' => $cs->teacher_id,
                    'remarks' => $status === 'returned' ? 'Please check the quarterly exam scores of 2 students.' : null]);
            }
            $n++;
        }
        $this->info("Grades added for $n classes.");
    }

    private function applications(SchoolYear $sy): void
    {
        $levels = GradeLevel::orderBy('sort_order')->get()->filter(fn ($g) => preg_match('/\d/', $g->name))->values();
        $types = ['new', 'transferee', 'new', 'returnee'];
        $schools = ['San Roque Elementary School', 'Holy Cross Academy', 'Rizal National High School', 'St. Mary’s School'];
        foreach (range(0, 11) as $i) {
            [$f, $m, $l] = DemoNames::for(2201 + $i);
            $g = $levels[$i % $levels->count()];
            $ref = 'APP-'.now()->format('y').str_pad((string) (500 + $i), 4, '0', STR_PAD_LEFT);
            Application::firstOrCreate(['reference_no' => $ref], [
                'school_year_id' => $sy->id, 'grade_level_id' => $g->id, 'applicant_type' => $types[$i % 4],
                'first_name' => $f, 'middle_name' => $m, 'last_name' => $l, 'sex' => $i % 2 ? 'Female' : 'Male',
                'birthdate' => now()->subYears(6 + $i % 12)->subDays($i * 13), 'birthplace' => 'Quezon City', 'address' => ($i + 12).' Kalayaan Ave., Quezon City',
                'previous_school' => $types[$i % 4] === 'new' && $i % 12 < 1 ? null : $schools[$i % 4],
                'guardian_name' => DemoNames::for(2300 + $i)[0].' '.$l, 'guardian_relationship' => $i % 3 ? 'Mother' : 'Father',
                'guardian_phone' => '0918'.str_pad((string) (2000000 + $i), 7, '0'), 'guardian_email' => 'applicant'.($i + 1).'@example.com',
                'status' => $i < 8 ? 'pending' : 'for_review', 'consent_at' => now()->subDays($i), 'created_at' => now()->subDays(12 - $i),
            ]);
        }
        $this->info('12 applications waiting for review.');
    }

    private function orders(SchoolYear $sy): void
    {
        $items = ShopItem::where('is_active', true)->get();
        if ($items->isEmpty()) {
            return;
        }
        $students = Student::whereHas('enrollments', fn ($q) => $q->where('school_year_id', $sy->id)->whereHas('account'))->with('user')->inRandomOrder()->take(14)->get();
        $plan = ['placed', 'placed', 'placed', 'approved', 'approved', 'ready', 'paid', 'paid', 'released', 'cancelled', 'walkin', 'walkin', 'walkin_paid', 'placed'];
        foreach ($students as $i => $st) {
            $account = $st->currentEnrollment()?->account;
            $kind = $plan[$i] ?? 'placed';
            $lines = $items->shuffle()->take(1 + $i % 3)->map(function ($it) {
                $size = $it->sizes ? $it->sizes[array_rand($it->sizes)] : null;

                return ['shop_item_id' => $it->id, 'name' => $it->name, 'size' => $size, 'qty' => 1 + mt_rand(0, 1), 'price' => $it->price];
            })->values();
            $total = round($lines->sum(fn ($l) => $l['price'] * $l['qty']), 2);
            $o = ShopOrder::create(['student_id' => $st->id, 'student_account_id' => $account->id, 'ordered_by' => $st->user_id ?? User::where('role', Role::BOOKSTORE)->value('id'), 'total' => $total,
                'walk_in' => str_starts_with($kind, 'walkin'), 'created_at' => now()->subHours(3 * $i)]);
            $o->lines()->createMany($lines->all());
            foreach ($lines as $l) {
                ShopItem::whereKey($l['shop_item_id'])->whereNotNull('stock')->where('stock', '>=', $l['qty'])->decrement('stock', $l['qty']);
            }
            if (in_array($kind, ['placed', 'cancelled'], true)) {
                $o->update($kind === 'cancelled' ? ['status' => 'cancelled', 'note' => 'Size M is out of stock.'] : []);

                continue;
            }
            $charge = AccountCharge::create(['student_account_id' => $account->id, 'shop_order_id' => $o->id, 'description' => 'Bookstore order #'.$o->id, 'amount' => $total]);
            $o->update(['status' => match ($kind) {
                'ready' => 'ready', 'released' => 'released', default => 'approved'
            }]);
            if (in_array($kind, ['paid', 'released', 'walkin_paid'], true)) {
                $p = $account->payments()->create(['amount' => $total, 'method' => 'cash', 'paid_on' => today(), 'purpose' => 'Bookstore order #'.$o->id,
                    'or_number' => Billing::nextOrNumber(), 'received_by' => User::where('role', Role::CASHIER)->value('id')]);
                Billing::allocate($p, 'order:'.$charge->id);
            }
        }
        $this->info($students->count().' bookstore orders (new, waiting for payment, paid, released, declined, walk-in).');
    }

    private function requests(SchoolYear $sy): void
    {
        $services = SchoolService::where('is_active', true)->get();
        $students = Student::whereHas('enrollments', fn ($q) => $q->where('school_year_id', $sy->id)->whereHas('account'))->inRandomOrder()->take(12)->get();
        $statuses = ['requested', 'requested', 'requested', 'processing', 'processing', 'ready', 'ready', 'released', 'declined', 'requested', 'processing', 'ready'];
        foreach ($students as $i => $st) {
            $svc = $services[$i % $services->count()];
            $account = $st->currentEnrollment()->account;
            $charge = $svc->fee > 0 ? AccountCharge::create(['student_account_id' => $account->id, 'description' => $svc->name, 'amount' => $svc->fee]) : null;
            ServiceRequest::create(['school_service_id' => $svc->id, 'student_id' => $st->id, 'requested_by' => $st->user_id, 'account_charge_id' => $charge?->id, 'copies' => 1,
                'purpose' => ['College application', 'Scholarship', 'Transfer', 'Personal copy'][$i % 4], 'status' => $statuses[$i],
                'note' => $statuses[$i] === 'declined' ? 'Please settle your library book first.' : null, 'created_at' => now()->subDays($i)]);
            if ($charge && in_array($statuses[$i], ['ready', 'released'], true) && $i % 2) {
                $p = $account->payments()->create(['amount' => $svc->fee, 'method' => 'cash', 'paid_on' => today(), 'purpose' => $svc->name,
                    'or_number' => Billing::nextOrNumber(), 'received_by' => User::where('role', Role::CASHIER)->value('id')]);
                Billing::allocate($p, 'order:'.$charge->id);
            }
        }
        $this->info($students->count().' document requests in every step.');
    }

    private function payroll(): void
    {
        $k = 0;
        foreach (User::whereIn('role', [Role::TEACHER, Role::REGISTRAR, Role::CASHIER, Role::BOOKSTORE, Role::HR, Role::GUARD, Role::LIBRARIAN])->where('is_active', true)->get() as $u) {
            if (Employee::where('user_id', $u->id)->exists()) {
                continue;
            }
            $parts = explode(' ', $u->name);
            Employee::create(['user_id' => $u->id, 'employee_no' => 'EMP-'.str_pad((string) (500 + $u->id), 4, '0', STR_PAD_LEFT), 'first_name' => $parts[0], 'last_name' => end($parts),
                'position' => $u->role === Role::TEACHER ? 'Teacher I' : ucfirst($u->role), 'department' => $u->role === Role::TEACHER ? 'Academics' : 'Administration',
                'monthly_salary' => $u->role === Role::TEACHER ? 27000 + ($u->id % 6) * 1500 : 18000 + ($u->id % 4) * 1000, 'date_hired' => now()->subYears(1 + $u->id % 12), 'status' => 'active']);
            $k++;
        }
        $start = now()->startOfMonth();
        if (! Payroll::whereDate('period_start', $start)->exists()) {
            $p = Payroll::create(['period_start' => $start, 'period_end' => $start->copy()->day(15), 'prepared_by' => User::where('role', Role::ADMIN)->value('id')]);
            foreach (Employee::where('status', 'active')->get() as $e) {
                $p->items()->create(['employee_id' => $e->id] + Calc::compute($e->monthly_salary, 0.5));
            }
        }
        $this->info("Employee records added: $k. Draft payroll for ".$start->format('M').' 1–15 ready to review.');
    }
}
