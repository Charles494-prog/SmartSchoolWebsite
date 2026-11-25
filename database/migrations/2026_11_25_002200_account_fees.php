<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fee lines a student was actually charged (snapshot taken when fees are assessed).
 * Before this, the account page listed today's fee items, which could disagree with the stored total.
 * Existing accounts are filled from the current fee items; where the stored total differs, the tuition line
 * takes the difference, so every account's lines add up to what it was charged (no balance changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_fees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('category', 30)->nullable();
            $t->decimal('amount', 12, 2);
        });

        foreach (DB::table('student_accounts')->join('enrollments', 'enrollments.id', '=', 'student_accounts.enrollment_id')
            ->select('student_accounts.id', 'student_accounts.gross_amount', 'enrollments.school_year_id', 'enrollments.grade_level_id')->get() as $a) {
            $fees = DB::table('fee_items')->where('school_year_id', $a->school_year_id)
                ->where(fn ($q) => $q->whereNull('grade_level_id')->orWhere('grade_level_id', $a->grade_level_id))->orderBy('name')->get();
            $lines = $fees->map(fn ($f) => ['student_account_id' => $a->id, 'name' => $f->name, 'category' => $f->category, 'amount' => (float) $f->amount])->values()->all();
            $diff = round((float) $a->gross_amount - array_sum(array_column($lines, 'amount')), 2);
            if (abs($diff) >= 0.01) {
                $i = collect($lines)->search(fn ($l) => $l['category'] === 'tuition');
                if ($i === false) {
                    $lines[] = ['student_account_id' => $a->id, 'name' => 'Tuition fee', 'category' => 'tuition', 'amount' => $diff];
                } else {
                    $lines[$i]['amount'] = round($lines[$i]['amount'] + $diff, 2);
                }
            }
            if ($lines) {
                DB::table('account_fees')->insert($lines);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_fees');
    }
};
