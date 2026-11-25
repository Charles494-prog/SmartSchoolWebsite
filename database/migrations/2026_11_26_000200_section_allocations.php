<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One run of "put this grade's students into sections": the plan, what it replaced (for undo), and how it was made.
        Schema::create('section_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_year_id')->constrained()->cascadeOnDelete();
            $t->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $t->string('method', 12)->default('random');      // random | choice | manual
            $t->string('status', 12)->default('draft');       // draft | open | committed | undone
            $t->unsignedInteger('seed')->default(1);
            $t->json('options')->nullable();
            $t->json('plan')->nullable();                     // enrollment_id => section_id
            $t->json('locked')->nullable();                   // enrollment ids kept in place
            $t->json('previous')->nullable();                 // enrollment_id => section_id before commit
            $t->dateTime('deadline')->nullable();              // student choice window
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('committed_at')->nullable();
            $t->dateTime('undone_at')->nullable();
            $t->timestamps();
            $t->index(['school_year_id', 'grade_level_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_allocations');
    }
};
