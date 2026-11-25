<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quiz builder for class cards (Online mode): questions (GIFT compatible), settings and student attempts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->unsignedSmallInteger('time_limit')->nullable();           // minutes; null = no limit
            $t->unsignedTinyInteger('attempts_allowed')->default(1);       // 0 = unlimited
            $t->boolean('shuffle_questions')->default(false);
            $t->boolean('shuffle_answers')->default(false);
            $t->string('show_results', 12)->default('after_submit');     // after_submit | after_due | never
        });
        Schema::create('quiz_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('type', 12);                                       // mc, multi, tf, short, numeric, matching, essay
            $t->string('title')->nullable();
            $t->text('text');
            $t->json('data');                                             // options / answers, by type
            $t->decimal('points', 7, 2)->default(1);
            $t->text('feedback')->nullable();
            $t->timestamps();
        });
        Schema::create('quiz_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('attempt_no')->default(1);
            $t->json('order')->nullable();                                // question order shown to this student
            $t->json('answers')->nullable();
            $t->json('marks')->nullable();                                // points per question
            $t->decimal('score', 7, 2)->nullable();
            $t->boolean('needs_review')->default(false);                  // has essays to check
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::table('assignments', fn (Blueprint $t) => $t->dropColumn(['time_limit', 'attempts_allowed', 'shuffle_questions', 'shuffle_answers', 'show_results']));
    }
};
