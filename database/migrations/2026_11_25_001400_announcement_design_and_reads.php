<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Announcements: picture with caption, event date, designed content (sandboxed) and "seen by" receipts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $t) {
            $t->string('image_path')->nullable();
            $t->string('image_caption', 255)->nullable();
            $t->dateTime('event_at')->nullable();
            $t->text('body_html')->nullable();
        });
        Schema::create('announcement_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at');
            $t->unique(['announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::table('announcements', fn (Blueprint $t) => $t->dropColumn(['image_path', 'image_caption', 'event_at', 'body_html']));
    }
};
