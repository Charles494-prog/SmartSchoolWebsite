<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A grade level / college program year can have its own passing score; null = the school's. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $t) {
            $t->decimal('passing_grade', 5, 2)->nullable()->after('grade_scale');
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $t) {
            $t->dropColumn('passing_grade');
        });
    }
};
