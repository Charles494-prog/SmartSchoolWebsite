<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Classwork posts on a class card: assignment, project, activity or material (no score). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->string('type', 20)->default('assignment')->after('class_subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->dropColumn('type');
        });
    }
};
