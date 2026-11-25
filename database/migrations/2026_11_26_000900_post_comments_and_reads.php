<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Class-card posts: comments (teacher can turn off, delete, or mute a student) and "seen by" receipts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->boolean('allow_comments')->default(true);
            $t->boolean('track_reads')->default(true);
        });
        Schema::create('post_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('body', 1000);
            $t->timestamps();
            $t->index(['assignment_id', 'created_at']);
        });
        Schema::create('post_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at');
            $t->unique(['assignment_id', 'user_id']);
        });
        Schema::create('class_comment_mutes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['class_subject_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_comment_mutes');
        Schema::dropIfExists('post_reads');
        Schema::dropIfExists('post_comments');
        Schema::table('assignments', function (Blueprint $t) {
            $t->dropColumn(['allow_comments', 'track_reads']);
        });
    }
};
