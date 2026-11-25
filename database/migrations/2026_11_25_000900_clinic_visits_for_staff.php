<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff can visit the clinic too: a visit belongs to a student OR an employee. Existing rows are kept. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_visits', function (Blueprint $t) {
            $t->foreignId('student_id')->nullable()->change();
            $t->foreignId('employee_id')->nullable()->after('student_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_visits', function (Blueprint $t) {
            $t->dropConstrainedForeignId('employee_id');
        });
    }
};
