<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $t) {
            $t->unsignedTinyInteger('at_weight')->default(0)->after('qa_weight');
        });
        // A class can use its own weights (approved by the admin) instead of the subject's.
        Schema::table('class_subjects', function (Blueprint $t) {
            $t->unsignedTinyInteger('ww_weight')->nullable();
            $t->unsignedTinyInteger('pt_weight')->nullable();
            $t->unsignedTinyInteger('qa_weight')->nullable();
            $t->unsignedTinyInteger('at_weight')->nullable();
        });
        Schema::create('grading_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('ww_weight');
            $t->unsignedTinyInteger('pt_weight');
            $t->unsignedTinyInteger('qa_weight');
            $t->unsignedTinyInteger('at_weight');
            $t->text('reason');
            $t->string('status', 12)->default('pending'); // pending, approved, rejected
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_requests');
        Schema::table('class_subjects', fn (Blueprint $t) => $t->dropColumn(['ww_weight', 'pt_weight', 'qa_weight', 'at_weight']));
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('at_weight'));
    }
};
