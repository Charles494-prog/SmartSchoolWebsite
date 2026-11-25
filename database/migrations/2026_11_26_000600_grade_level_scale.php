<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A grade level (or college program year) can use its own grading scale; null = the school's scale. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $t) {
            $t->json('grade_scale')->nullable()->after('numeric_grades');
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $t) {
            $t->dropColumn('grade_scale');
        });
    }
};
