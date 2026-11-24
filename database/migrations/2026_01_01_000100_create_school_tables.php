<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 40);
            $t->string('auditable_type')->nullable();
            $t->unsignedBigInteger('auditable_id')->nullable();
            $t->string('description')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('school_years', function (Blueprint $t) {
            $t->id();
            $t->string('name', 20)->unique();
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('is_current')->default(false);
            $t->timestamps();
        });

        Schema::create('grade_levels', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40);
            $t->string('department', 20); // kinder, elementary, jhs, shs, college, other
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->unsignedTinyInteger('terms')->default(4); // grading periods per year
            $t->boolean('numeric_grades')->default(true);
            $t->foreignId('next_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('subjects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30);
            $t->string('name');
            $t->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('ww_weight')->default(30);
            $t->unsignedTinyInteger('pt_weight')->default(50);
            $t->unsignedTinyInteger('qa_weight')->default(20);
            $t->unsignedTinyInteger('semester')->nullable(); // SHS: 1 or 2
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('sections', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->foreignId('grade_level_id')->constrained();
            $t->foreignId('school_year_id')->constrained();
            $t->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('room', 40)->nullable();
            $t->unsignedSmallInteger('capacity')->default(40);
            $t->timestamps();
            $t->unique(['name', 'school_year_id']);
        });

        Schema::create('students', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('student_no', 30)->unique();
            $t->string('lrn', 12)->nullable()->unique();
            $t->string('first_name');
            $t->string('middle_name')->nullable();
            $t->string('last_name');
            $t->string('suffix', 10)->nullable();
            $t->string('sex', 6);
            $t->date('birthdate');
            $t->string('birthplace')->nullable();
            $t->string('address')->nullable();
            $t->string('religion')->nullable();
            $t->string('nationality')->default('Filipino');
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('medical_notes')->nullable();
            $t->string('photo_path')->nullable();
            $t->string('status', 20)->default('active'); // active, transferred, graduated, dropped
            $t->string('qr_token', 40)->unique();
            $t->timestamps();
            $t->index(['last_name', 'first_name']);
        });

        Schema::create('guardians', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->string('occupation')->nullable();
            $t->string('address')->nullable();
            $t->timestamps();
        });

        Schema::create('guardian_student', function (Blueprint $t) {
            $t->id();
            $t->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->string('relationship', 30);
            $t->boolean('is_primary')->default(false);
            $t->boolean('is_emergency_contact')->default(false);
            $t->unique(['guardian_id', 'student_id']);
        });

        Schema::create('applications', function (Blueprint $t) {
            $t->id();
            $t->string('reference_no', 20)->unique();
            $t->foreignId('school_year_id')->constrained();
            $t->foreignId('grade_level_id')->constrained();
            $t->string('applicant_type', 20)->default('new'); // new, transferee, returnee
            $t->string('lrn', 12)->nullable();
            $t->string('first_name');
            $t->string('middle_name')->nullable();
            $t->string('last_name');
            $t->string('suffix', 10)->nullable();
            $t->string('sex', 6);
            $t->date('birthdate');
            $t->string('birthplace')->nullable();
            $t->string('address');
            $t->string('previous_school')->nullable();
            $t->string('guardian_name');
            $t->string('guardian_relationship', 30);
            $t->string('guardian_phone', 30);
            $t->string('guardian_email');
            $t->string('status', 20)->default('pending'); // pending, for_review, approved, rejected
            $t->text('remarks')->nullable();
            $t->timestamp('consent_at');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('application_documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('application_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40); // birth_certificate, sf10, report_card, photo, other
            $t->string('path');
            $t->string('original_name');
            $t->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_year_id')->constrained();
            $t->foreignId('grade_level_id')->constrained();
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('enrolled'); // enrolled, promoted, retained, transferred_out, dropped, graduated
            $t->date('enrolled_on');
            $t->decimal('general_average', 5, 2)->nullable();
            $t->timestamps();
            $t->unique(['student_id', 'school_year_id']);
        });

        Schema::create('transfers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->string('direction', 3); // in, out
            $t->string('school_name');
            $t->date('transfer_date');
            $t->text('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('class_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('section_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['section_id', 'subject_id']);
        });

        Schema::create('schedule_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('day_of_week'); // 1 = Monday … 7 = Sunday
            $t->time('starts_at');
            $t->time('ends_at');
            $t->string('room', 40)->nullable();
            $t->timestamps();
        });

        Schema::create('assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('term');
            $t->string('component', 2); // ww, pt, qa
            $t->string('title');
            $t->decimal('max_score', 7, 2);
            $t->date('given_on')->nullable();
            $t->timestamps();
        });

        Schema::create('scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->decimal('score', 7, 2)->nullable();
            $t->timestamps();
            $t->unique(['assessment_id', 'student_id']);
        });

        Schema::create('grade_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('term');
            $t->string('status', 20)->default('submitted'); // submitted, approved, returned
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('remarks')->nullable();
            $t->timestamps();
            $t->unique(['class_subject_id', 'term']);
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('class_subject_id')->nullable()->constrained()->nullOnDelete();
            $t->date('date');
            $t->string('status', 10); // present, late, absent, excused
            $t->time('time_in')->nullable();
            $t->string('method', 10)->default('manual'); // manual, qr
            $t->string('remarks')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['student_id', 'date', 'class_subject_id'], 'attendance_unique');
            $t->index(['section_id', 'date']);
        });

        Schema::create('fee_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_year_id')->constrained();
            $t->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete(); // null = all levels
            $t->string('name');
            $t->string('category', 30)->default('tuition'); // tuition, miscellaneous, other
            $t->decimal('amount', 10, 2);
            $t->timestamps();
        });

        Schema::create('discounts', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type', 10); // percent, fixed
            $t->decimal('value', 10, 2);
            $t->boolean('tuition_only')->default(true);
            $t->boolean('is_scholarship')->default(false);
            $t->timestamps();
        });

        Schema::create('student_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('payment_plan', 20)->default('full'); // full, semestral, quarterly, monthly
            $t->decimal('gross_amount', 10, 2)->default(0);
            $t->decimal('discount_amount', 10, 2)->default(0);
            $t->decimal('net_amount', 10, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('account_discounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
        });

        Schema::create('installments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->string('label', 40);
            $t->date('due_on');
            $t->decimal('amount', 10, 2);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->string('or_number', 30)->unique();
            $t->decimal('amount', 10, 2);
            $t->string('method', 20)->default('cash'); // cash, gcash, bank, check
            $t->string('reference', 60)->nullable();
            $t->date('paid_on');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_void')->default(false);
            $t->string('void_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->string('audience', 20)->default('all'); // all, staff, teachers, students, parents
            $t->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('is_pinned')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('type', 20)->default('event'); // event, exam, holiday, meeting
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->string('subject');
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('last_read_at')->nullable();
            $t->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('books', function (Blueprint $t) {
            $t->id();
            $t->string('isbn', 20)->nullable();
            $t->string('title');
            $t->string('author')->nullable();
            $t->string('publisher')->nullable();
            $t->string('category')->nullable();
            $t->unsignedSmallInteger('copies')->default(1);
            $t->string('shelf', 30)->nullable();
            $t->timestamps();
        });

        Schema::create('book_loans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); // staff borrower
            $t->date('borrowed_on');
            $t->date('due_on');
            $t->date('returned_on')->nullable();
            $t->decimal('fine', 8, 2)->default(0);
            $t->timestamps();
        });

        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('instructions')->nullable();
            $t->string('attachment_path')->nullable();
            $t->timestamp('due_at');
            $t->decimal('max_score', 7, 2)->default(100);
            $t->timestamps();
        });

        Schema::create('submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->string('file_path')->nullable();
            $t->timestamp('submitted_at');
            $t->decimal('score', 7, 2)->nullable();
            $t->text('feedback')->nullable();
            $t->timestamps();
            $t->unique(['assignment_id', 'student_id']);
        });

        Schema::create('clinic_visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->dateTime('visited_at');
            $t->string('complaint');
            $t->text('assessment')->nullable();
            $t->text('treatment')->nullable();
            $t->boolean('sent_home')->default(false);
            $t->boolean('parent_notified')->default(false);
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('guidance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->date('recorded_on');
            $t->string('type', 30); // counseling, incident, referral, follow_up
            $t->string('title');
            $t->text('notes');
            $t->boolean('is_confidential')->default(true);
            $t->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('employee_no', 30)->unique();
            $t->string('first_name');
            $t->string('last_name');
            $t->string('position');
            $t->string('department')->nullable();
            $t->string('employment_type', 20)->default('regular'); // regular, probationary, contractual, part_time
            $t->date('date_hired')->nullable();
            $t->decimal('monthly_salary', 10, 2)->default(0);
            $t->string('tin', 20)->nullable();
            $t->string('sss_no', 20)->nullable();
            $t->string('philhealth_no', 20)->nullable();
            $t->string('pagibig_no', 20)->nullable();
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 20)->default('draft'); // draft, finalized
            $t->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->decimal('basic_pay', 10, 2);
            $t->decimal('allowances', 10, 2)->default(0);
            $t->decimal('sss', 10, 2)->default(0);
            $t->decimal('philhealth', 10, 2)->default(0);
            $t->decimal('pagibig', 10, 2)->default(0);
            $t->decimal('withholding_tax', 10, 2)->default(0);
            $t->decimal('other_deductions', 10, 2)->default(0);
            $t->decimal('net_pay', 10, 2);
            $t->unique(['payroll_id', 'employee_id']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'notifications', 'payroll_items', 'payrolls', 'employees', 'guidance_records', 'clinic_visits',
            'submissions', 'assignments', 'book_loans', 'books', 'messages', 'conversation_user', 'conversations',
            'events', 'announcements', 'payments', 'installments', 'account_discounts', 'student_accounts',
            'discounts', 'fee_items', 'attendance_records', 'grade_submissions', 'scores', 'assessments',
            'schedule_slots', 'class_subjects', 'transfers', 'enrollments', 'application_documents',
            'applications', 'guardian_student', 'guardians', 'students', 'sections', 'subjects',
            'grade_levels', 'school_years', 'audit_logs', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
