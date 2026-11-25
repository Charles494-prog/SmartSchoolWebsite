<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each parent may see/do for a student – chosen by the student. Null = everything (older links). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guardian_student', fn (Blueprint $t) => $t->json('access')->nullable());
    }

    public function down(): void
    {
        Schema::table('guardian_student', fn (Blueprint $t) => $t->dropColumn('access'));
    }
};
