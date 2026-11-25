<?php

namespace App\Crud;

use App\Models;
use App\Notifications\GeneralNotice;
use App\Services\StaffAccounts;
use App\Support\Role;
use App\Support\School;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Simple list/create/edit/delete screens, described as data.
 * Field types: text, number, date, datetime-local, time, email, select, textarea, checkbox, password.
 */
class Resources
{
    public static function get(string $slug): ?array
    {
        return static::all()[$slug] ?? null;
    }

    public static function all(): array
    {
        $levels = fn () => Models\GradeLevel::orderBy('sort_order')->pluck('name', 'id')->all();
        $years = fn () => Models\SchoolYear::orderByDesc('starts_on')->pluck('name', 'id')->all();
        $students = fn () => Models\Student::where('status', 'active')->orderBy('last_name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->full_name.' ('.$s->student_no.')'])->all();

        return [
            'school-years' => [
                'model' => Models\SchoolYear::class, 'title' => 'School years', 'singular' => 'school year',
                'roles' => [Role::ADMIN], 'order' => ['starts_on', 'desc'], 'search' => ['name'],
                'help' => 'Tick "Current" on the school year in progress. Enrollment, fees and reports use the current school year.',
                'columns' => ['name' => 'School year', 'starts_on' => 'Starts', 'ends_on' => 'Ends', 'is_current' => 'Current'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'rules' => 'required|max:20', 'placeholder' => '2026-2027'],
                    ['name' => 'enrollment_opens_on', 'label' => 'Registration opens on', 'type' => 'date', 'rules' => 'nullable|date|before_or_equal:starts_on', 'help' => 'When families can start applying. Leave empty to open now.'],
                    ['name' => 'starts_on', 'label' => 'First day of classes', 'type' => 'date', 'rules' => 'required|date'],
                    ['name' => 'ends_on', 'label' => 'Last day of classes', 'type' => 'date', 'rules' => 'required|date|after:starts_on'],
                    ['name' => 'is_current', 'label' => 'This is the current school year', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
                'after_save' => function ($m) {
                    if ($m->is_current) {
                        Models\SchoolYear::where('id', '!=', $m->id)->update(['is_current' => false]);
                    }
                },
            ],
            'rooms' => [
                'model' => Models\Room::class, 'title' => 'Rooms', 'singular' => 'room',
                'roles' => [Role::ADMIN, Role::REGISTRAR], 'order' => ['name', 'asc'], 'search' => ['name', 'building'],
                'help' => 'List every classroom once. Sections and class schedules then pick a room from a dropdown, and a section cannot have more students than its room holds.',
                'columns' => ['name' => 'Room', 'building' => 'Building / floor', 'capacity' => 'Seats', 'is_active' => 'In use'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Room name', 'rules' => fn ($m) => ['required', 'max:40', Rule::unique('rooms', 'name')->ignore($m?->id)], 'placeholder' => 'Room 101'],
                    ['name' => 'building', 'label' => 'Building / floor (optional)', 'rules' => 'nullable|max:80', 'placeholder' => 'Main building, 1st floor'],
                    ['name' => 'capacity', 'label' => 'Seats (capacity)', 'type' => 'number', 'rules' => 'required|integer|min:1|max:500', 'default' => fn () => 40],
                    ['name' => 'is_active', 'label' => 'In use (untick to hide it from dropdowns)', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
            ],
            'grade-levels' => [
                'model' => Models\GradeLevel::class, 'title' => 'Grade levels', 'singular' => 'grade level',
                'roles' => [Role::ADMIN], 'order' => ['sort_order', 'asc'], 'search' => ['name'], 'with' => ['next'],
                'help' => '"Promotes to" decides where students go after passing. Leave it empty for the last level (they graduate).',
                'columns' => ['sort_order' => '#', 'name' => 'Grade level', 'department' => 'Department', 'terms' => 'Grading periods', 'next.name' => 'Promotes to'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'rules' => 'required|max:40', 'placeholder' => 'Grade 7'],
                    ['name' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => fn () => Models\GradeLevel::DEPARTMENTS, 'rules' => 'required'],
                    ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'required|integer|min:0'],
                    ['name' => 'terms', 'label' => 'Grading periods per year', 'type' => 'number', 'rules' => 'required|integer|min:1|max:6', 'help' => 'How many grading periods (e.g. 4 quarters).'],
                    ['name' => 'numeric_grades', 'label' => 'Uses numeric grades (untick for Kindergarten)', 'type' => 'checkbox', 'rules' => 'boolean'],
                    ['name' => 'next_grade_level_id', 'label' => 'Promotes to', 'type' => 'select', 'options' => $levels, 'rules' => 'nullable|exists:grade_levels,id'],
                ],
            ],
            'subjects' => [
                'model' => Models\Subject::class, 'title' => 'Subjects', 'singular' => 'subject',
                'roles' => [Role::ADMIN, Role::REGISTRAR], 'order' => ['name', 'asc'], 'search' => ['code', 'name'], 'with' => ['gradeLevel'],
                'help' => 'Weights decide how much quizzes, projects, exams and attendance count toward the grade. They must add up to 100 (for example 30 / 50 / 20 / 0). Teachers can ask for different weights for their own class; you approve them in Grades.',
                'filters' => ['grade_level_id' => ['label' => 'Grade level', 'options' => $levels]],
                'columns' => ['name' => 'Subject', 'gradeLevel.name' => 'Grade level', 'ww_weight' => 'Quizzes %', 'pt_weight' => 'Projects %', 'qa_weight' => 'Exams %', 'at_weight' => 'Attendance %'],
                'fields' => [
                    ['name' => 'code', 'label' => 'Code', 'rules' => 'required|max:30', 'placeholder' => 'MATH7'],
                    ['name' => 'name', 'label' => 'Subject name', 'rules' => 'required|max:120'],
                    ['name' => 'grade_level_id', 'label' => 'Grade level', 'type' => 'select', 'options' => $levels, 'rules' => 'nullable|exists:grade_levels,id'],
                    ['name' => 'ww_weight', 'label' => 'Quizzes & seatwork %', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100'],
                    ['name' => 'pt_weight', 'label' => 'Projects & performance %', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100'],
                    ['name' => 'qa_weight', 'label' => 'Exams / summative %', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100'],
                    ['name' => 'at_weight', 'label' => 'Attendance %', 'type' => 'number', 'rules' => 'required|integer|min:0|max:100', 'default' => fn () => 0],
                    ['name' => 'semester', 'label' => 'Semester (SHS only)', 'type' => 'select', 'options' => fn () => [1 => '1st semester', 2 => '2nd semester'], 'rules' => 'nullable|in:1,2'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
                'validate' => function (array $d) {
                    return ((int) $d['ww_weight'] + (int) $d['pt_weight'] + (int) $d['qa_weight'] + (int) ($d['at_weight'] ?? 0)) === 100 ? [] : ['ww_weight' => 'The weights must add up to 100.'];
                },
            ],
            'users' => [
                'model' => Models\User::class, 'title' => 'User accounts', 'singular' => 'user',
                'roles' => [Role::ADMIN], 'order' => ['name', 'asc'], 'search' => ['name', 'email'],
                'help' => 'Parent and student accounts are created automatically from the student profile or an approved application. A new password forces the user to change it at next login.',
                'filters' => ['role' => ['label' => 'Role', 'options' => fn () => Role::LABELS]],
                'columns' => ['name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'is_active' => 'Active', 'last_login_at' => 'Last login'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Full name', 'rules' => 'required|max:120'],
                    ['name' => 'email', 'label' => 'Email (used to log in)', 'type' => 'email', 'rules' => fn ($m) => ['required', 'email', Rule::unique('users', 'email')->ignore($m?->id)]],
                    ['name' => 'phone', 'label' => 'Mobile no.', 'rules' => 'nullable|max:30'],
                    ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => fn () => Role::LABELS, 'rules' => ['required', Rule::in(array_keys(Role::LABELS))]],
                    ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => fn ($m) => $m ? 'nullable|min:8' : 'required|min:8', 'help' => 'Leave empty to keep the current password.'],
                    ['name' => 'is_active', 'label' => 'Account active (untick to block login)', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
                'saving' => function (array $d, $m) {
                    if (empty($d['password'])) {
                        unset($d['password']);
                    } else {
                        $d['must_change_password'] = $m?->id !== auth()->id();
                    }

                    return $d;
                },
                'can_delete' => fn ($m) => $m->id !== auth()->id(),
            ],
            'fee-items' => [
                'model' => Models\FeeItem::class, 'title' => 'Fees', 'singular' => 'fee',
                'roles' => [Role::ADMIN, Role::CASHIER], 'order' => ['name', 'asc'], 'search' => ['name'], 'with' => ['gradeLevel', 'schoolYear'],
                'help' => 'Fees are added to each student\'s statement of account for the school year. Leave Grade level empty to charge every level.',
                'filters' => ['school_year_id' => ['label' => 'School year', 'options' => $years], 'grade_level_id' => ['label' => 'Grade level', 'options' => $levels]],
                'columns' => ['schoolYear.name' => 'School year', 'gradeLevel.name' => 'Grade level', 'name' => 'Fee', 'category' => 'Category', 'amount' => 'Amount'],
                'fields' => [
                    ['name' => 'school_year_id', 'label' => 'School year', 'type' => 'select', 'options' => $years, 'rules' => 'required|exists:school_years,id', 'default' => fn () => Models\SchoolYear::current()?->id],
                    ['name' => 'grade_level_id', 'label' => 'Grade level (empty = all levels)', 'type' => 'select', 'options' => $levels, 'rules' => 'nullable|exists:grade_levels,id'],
                    ['name' => 'name', 'label' => 'Fee name', 'rules' => 'required|max:120', 'placeholder' => 'Tuition fee'],
                    ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => fn () => ['tuition' => 'Tuition', 'miscellaneous' => 'Miscellaneous', 'other' => 'Other'], 'rules' => 'required'],
                    ['name' => 'amount', 'label' => 'Amount (₱)', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric|min:0'],
                ],
            ],
            'payment-plans' => [
                'model' => Models\PaymentPlan::class, 'title' => 'Payment plans', 'singular' => 'payment plan',
                'roles' => [Role::ADMIN, Role::CASHIER], 'order' => ['installments', 'asc'], 'search' => ['name'],
                'help' => 'How the total is divided. Example: 10 payments, 1 month apart, 20% down payment = 20% at enrollment, the rest in 9 equal monthly payments. A surcharge adds a % for paying in installments. You can still change any single student\'s schedule by hand on their account.',
                'columns' => ['name' => 'Plan', 'installments' => 'Payments', 'months_between' => 'Months apart', 'downpayment_percent' => 'Down payment %', 'surcharge_percent' => 'Surcharge %', 'is_active' => 'Offered'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Plan name', 'rules' => 'required|max:60', 'placeholder' => 'Monthly (10 payments)'],
                    ['name' => 'installments', 'label' => 'Number of payments', 'type' => 'number', 'rules' => 'required|integer|min:1|max:24', 'default' => fn () => 1],
                    ['name' => 'months_between', 'label' => 'Months between payments', 'type' => 'number', 'rules' => 'required|integer|min:1|max:12', 'default' => fn () => 1],
                    ['name' => 'downpayment_percent', 'label' => 'Down payment (% of total, 0 = equal parts)', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric|min:0|max:100', 'default' => fn () => 0],
                    ['name' => 'surcharge_percent', 'label' => 'Installment surcharge %', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric|min:0|max:50', 'default' => fn () => 0],
                    ['name' => 'is_active', 'label' => 'Offer this plan', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
            ],
            'discounts' => [
                'model' => Models\Discount::class, 'title' => 'Discounts & scholarships', 'singular' => 'discount',
                'roles' => [Role::ADMIN, Role::CASHIER], 'order' => ['name', 'asc'], 'search' => ['name'],
                'columns' => ['name' => 'Name', 'type' => 'Type', 'value' => 'Value', 'tuition_only' => 'Tuition only', 'is_scholarship' => 'Scholarship'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'rules' => 'required|max:120', 'placeholder' => 'Sibling discount'],
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => fn () => ['percent' => 'Percent (%)', 'fixed' => 'Fixed amount (₱)'], 'rules' => 'required|in:percent,fixed'],
                    ['name' => 'value', 'label' => 'Value', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric|min:0'],
                    ['name' => 'tuition_only', 'label' => 'Apply to tuition only (not miscellaneous fees)', 'type' => 'checkbox', 'rules' => 'boolean'],
                    ['name' => 'is_scholarship', 'label' => 'This is a scholarship', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
            ],
            'events' => [
                'model' => Models\Event::class, 'title' => 'Calendar events', 'singular' => 'event',
                'roles' => [Role::ADMIN, Role::REGISTRAR], 'order' => ['starts_on', 'desc'], 'search' => ['title'],
                'columns' => ['starts_on' => 'Date', 'ends_on' => 'Until', 'title' => 'Title', 'type' => 'Type'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'rules' => 'required|max:150'],
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => fn () => Models\Event::TYPES, 'rules' => 'required'],
                    ['name' => 'starts_on', 'label' => 'Date', 'type' => 'date', 'rules' => 'required|date'],
                    ['name' => 'ends_on', 'label' => 'Until (optional)', 'type' => 'date', 'rules' => 'nullable|date|after_or_equal:starts_on'],
                    ['name' => 'description', 'label' => 'Details', 'type' => 'textarea', 'rules' => 'nullable|max:2000'],
                ],
            ],
            'transfers' => [
                'model' => Models\Transfer::class, 'title' => 'Transfer records', 'singular' => 'transfer record',
                'roles' => [Role::ADMIN, Role::REGISTRAR], 'order' => ['transfer_date', 'desc'], 'search' => ['school_name'], 'with' => ['student'],
                'help' => 'Each transfer is its own record (history). You can fix the school name, date or reason, but not the student or the direction – if a student moves again, add a new transfer record.',
                'columns' => ['transfer_date' => 'Date', 'student.full_name' => 'Student', 'direction' => 'In/Out', 'school_name' => 'School', 'reason' => 'Reason'],
                'fields' => [
                    ['name' => 'student_id', 'label' => 'Student', 'type' => 'select', 'options' => fn () => Models\Student::orderBy('last_name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all(), 'rules' => 'required|exists:students,id', 'locked' => true],
                    ['name' => 'direction', 'label' => 'Direction', 'type' => 'select', 'options' => fn () => ['in' => 'Transferred in', 'out' => 'Transferred out'], 'rules' => 'required|in:in,out', 'locked' => true],
                    ['name' => 'school_name', 'label' => 'Other school', 'rules' => 'required|max:200'],
                    ['name' => 'transfer_date', 'label' => 'Date', 'type' => 'date', 'rules' => 'required|date'],
                    ['name' => 'reason', 'label' => 'Reason', 'type' => 'textarea', 'rules' => 'nullable|max:1000'],
                ],
            ],
            'clinic-visits' => [
                'model' => Models\ClinicVisit::class, 'title' => 'Clinic visits', 'singular' => 'clinic visit',
                'roles' => [Role::ADMIN, Role::NURSE], 'order' => ['visited_at', 'desc'], 'search' => ['complaint'], 'with' => ['student', 'employee'],
                'help' => 'Students and staff. Health information is sensitive personal information under the Data Privacy Act. Only the nurse and administrator can see these records.',
                'columns' => ['visited_at' => 'When', 'patient_name' => 'Patient', 'complaint' => 'Complaint', 'treatment' => 'Treatment', 'sent_home' => 'Sent home'],
                'fields' => array_values(array_filter([
                    ['name' => 'student_id', 'label' => 'Student', 'type' => 'select', 'options' => $students, 'help' => 'Pick a student or a staff member.', 'rules' => 'nullable|required_without:employee_id|prohibits:employee_id|exists:students,id'],
                    ['name' => 'employee_id', 'label' => 'Staff member', 'type' => 'select', 'options' => fn () => Models\Employee::where('status', '!=', 'resigned')->orderBy('last_name')->get()->mapWithKeys(fn ($e) => [$e->id => $e->full_name.' – '.$e->position])->all(), 'rules' => 'nullable|required_without:student_id|exists:employees,id'],
                    ['name' => 'visited_at', 'label' => 'Date & time', 'type' => 'datetime-local', 'rules' => 'required|date', 'default' => fn () => now()->format('Y-m-d\TH:i')],
                    ['name' => 'complaint', 'label' => 'Complaint', 'rules' => 'required|max:200'],
                    ['name' => 'assessment', 'label' => 'Assessment', 'type' => 'textarea', 'rules' => 'nullable|max:2000'],
                    ['name' => 'treatment', 'label' => 'Treatment / action', 'type' => 'textarea', 'rules' => 'nullable|max:2000'],
                    ['name' => 'sent_home', 'label' => 'Sent home', 'type' => 'checkbox', 'rules' => 'boolean'],
                    // Parents have no portal accounts or email on the school-network-only (LAN) setup.
                    School::allows('parent_accounts') ? ['name' => 'parent_notified', 'label' => 'Notify parent/guardian now (portal + email) – students only', 'type' => 'checkbox', 'rules' => 'boolean'] : null,
                ])),
                'saving' => function (array $d, $m) {
                    $d['recorded_by'] ??= auth()->id();
                    if (! empty($d['employee_id']) || ! School::allows('parent_accounts')) {
                        $d['parent_notified'] = false;
                    }

                    return $d;
                },
                'after_save' => function ($m) {
                    if (! $m->student || ! School::allows('parent_accounts')) {
                        return;
                    }
                    if ($m->parent_notified && $m->wasChanged('parent_notified') || ($m->wasRecentlyCreated && $m->parent_notified)) {
                        $users = $m->student->guardians()->whereNotNull('user_id')->with('user')->get()->pluck('user')->filter();
                        Notification::send($users, new GeneralNotice(
                            'Clinic visit', "{$m->student->first_name} visited the school clinic ({$m->complaint})".($m->sent_home ? ' and was sent home' : '').'. Please contact the school nurse for details.', '/portal', true));
                    }
                },
            ],
            'guidance-records' => [
                'model' => Models\GuidanceRecord::class, 'title' => 'Guidance records', 'singular' => 'guidance record',
                'roles' => [Role::GUIDANCE], 'order' => ['recorded_on', 'desc'], 'search' => ['title'], 'with' => ['student'],
                'help' => 'Confidential. Only guidance counselors can open these records (not even administrators).',
                'columns' => ['recorded_on' => 'Date', 'student.full_name' => 'Student', 'type' => 'Type', 'title' => 'Title'],
                'fields' => [
                    ['name' => 'student_id', 'label' => 'Student', 'type' => 'select', 'options' => $students, 'rules' => 'required|exists:students,id'],
                    ['name' => 'recorded_on', 'label' => 'Date', 'type' => 'date', 'rules' => 'required|date', 'default' => fn () => now()->toDateString()],
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => fn () => Models\GuidanceRecord::TYPES, 'rules' => 'required'],
                    ['name' => 'title', 'label' => 'Title', 'rules' => 'required|max:200'],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => 'required|max:10000'],
                    ['name' => 'is_confidential', 'label' => 'Confidential', 'type' => 'checkbox', 'rules' => 'boolean'],
                ],
                'saving' => function (array $d, $m) {
                    $d['counselor_id'] ??= auth()->id();

                    return $d;
                },
            ],
            'employees' => [
                'saving' => function (array $d, $m) {
                    unset($d['login_email'], $d['login_role']);
                    if (blank($d['employee_no'] ?? null)) {
                        $d['employee_no'] = $m?->employee_no ?: Models\Employee::nextNumber();
                    }

                    return $d;
                },
                'after_save' => fn ($m) => StaffAccounts::sync($m, request('login_email'), request('login_role')),
                'model' => Models\Employee::class, 'title' => 'Employees', 'singular' => 'employee',
                'roles' => [Role::ADMIN, Role::HR], 'order' => ['last_name', 'asc'], 'search' => ['first_name', 'last_name', 'employee_no', 'position'],
                'columns' => ['employee_no' => 'Emp. no.', 'full_name' => 'Name', 'position' => 'Position', 'department' => 'Department', 'employment_type' => 'Type', 'monthly_salary' => 'Monthly salary', 'status' => 'Status'],
                'fields' => [
                    ['name' => 'employee_no', 'label' => 'Employee no.', 'placeholder' => 'Automatic', 'help' => 'Leave empty to number automatically (e.g. EMP-2026-0001).', 'rules' => fn ($m) => ['nullable', 'max:30', Rule::unique('employees', 'employee_no')->ignore($m?->id)]],
                    ['name' => 'first_name', 'label' => 'First name', 'rules' => 'required|max:80'],
                    ['name' => 'last_name', 'label' => 'Last name', 'rules' => 'required|max:80'],
                    ['name' => 'position', 'label' => 'Position', 'rules' => 'required|max:100', 'placeholder' => 'Teacher I'],
                    ['name' => 'department', 'label' => 'Department', 'rules' => 'nullable|max:100'],
                    ['name' => 'employment_type', 'label' => 'Employment type', 'type' => 'select', 'options' => fn () => Models\Employee::TYPES, 'rules' => 'required'],
                    ['name' => 'date_hired', 'label' => 'Date hired', 'type' => 'date', 'rules' => 'nullable|date'],
                    ['name' => 'monthly_salary', 'label' => 'Monthly salary (₱)', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric|min:0'],
                    ['name' => 'tin', 'label' => 'TIN', 'rules' => 'nullable|max:20'],
                    ['name' => 'sss_no', 'label' => 'SSS no.', 'rules' => 'nullable|max:20'],
                    ['name' => 'philhealth_no', 'label' => 'PhilHealth no.', 'rules' => 'nullable|max:20'],
                    ['name' => 'pagibig_no', 'label' => 'Pag-IBIG no.', 'rules' => 'nullable|max:20'],
                    ['name' => 'login_email', 'label' => 'Login email', 'type' => 'email', 'help' => 'Leave empty if this person does not use SmartSchool. A temporary password is created and shown once; they must change it at first login.',
                        'rules' => fn ($m) => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($m?->user_id)], 'placeholder' => 'name@school.edu.ph'],
                    ['name' => 'login_role', 'label' => 'What can they open?', 'type' => 'select', 'options' => fn () => Role::staffChoices(auth()->user()), 'rules' => fn () => ['nullable', 'required_with:login_email', Rule::in(array_keys(Role::staffChoices(auth()->user())))]],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => fn () => ['active' => 'Active', 'on_leave' => 'On leave', 'resigned' => 'Resigned'], 'rules' => 'required'],
                ],
            ],
        ];
    }
}
