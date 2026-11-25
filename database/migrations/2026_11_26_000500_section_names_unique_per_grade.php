<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Section A" can exist in Grade 1 and Grade 2 of the same year: names are unique per grade level, not per year. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $t) {
            $t->unique(['name', 'school_year_id', 'grade_level_id']);
        });
        Schema::table('sections', function (Blueprint $t) {
            $t->dropUnique(['name', 'school_year_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $t) {
            $t->unique(['name', 'school_year_id']);
        });
        Schema::table('sections', function (Blueprint $t) {
            $t->dropUnique(['name', 'school_year_id', 'grade_level_id']);
        });
    }
};
