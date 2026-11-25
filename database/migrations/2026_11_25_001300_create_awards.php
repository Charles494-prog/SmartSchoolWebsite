<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Special awards (set up by the admin) and teacher nominations the admin approves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('awards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('description', 255)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('award_nominations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('award_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('term')->nullable();      // null = whole year
            $t->text('reason')->nullable();
            $t->string('status', 10)->default('pending');     // pending | approved | rejected
            $t->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('award_nominations');
        Schema::dropIfExists('awards');
    }
};
