<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the receipts list (by date), overdue payments and library returns. Additive only. */
return new class extends Migration
{
    private const INDEXES = [
        'payments' => [['paid_on', 'is_void']],
        'installments' => [['due_on']],
        'book_loans' => [['returned_on', 'due_on']],
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
