<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the filters and joins the dashboards, lists and reports use most. */
return new class extends Migration
{
    private const INDEXES = [
        'enrollments' => [['school_year_id', 'status'], ['section_id', 'status'], ['grade_level_id']],
        'attendance_records' => [['date', 'status'], ['class_subject_id', 'date']],
        'applications' => [['status']],
        'schedule_slots' => [['day_of_week', 'starts_at']],
        'fee_items' => [['school_year_id', 'grade_level_id']],
        'payments' => [['student_account_id', 'is_void']],
        'messages' => [['conversation_id', 'created_at']],
        'announcements' => [['published_at']],
        'events' => [['starts_on']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $sets) {
                foreach ($sets as $cols) {
                    if (Schema::hasColumns($table, $cols) && ! Schema::hasIndex($table, $cols)) {
                        $t->index($cols, $table.'_'.implode('_', $cols).'_perf');
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $sets) {
                foreach ($sets as $cols) {
                    if (Schema::hasIndex($table, $table.'_'.implode('_', $cols).'_perf')) {
                        $t->dropIndex($table.'_'.implode('_', $cols).'_perf');
                    }
                }
            });
        }
    }
};
