<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class cards, part 2 (Online mode): announcements and documents in the stream, classwork that feeds the
 * gradebook, teacher-designed content (HTML/CSS/JS shown in a sandbox) and card colours.
 * Existing rows keep working: new columns are nullable or have defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $t) {
            $t->timestamp('due_at')->nullable()->change();          // announcements / documents have no due date
            $t->decimal('max_score', 7, 2)->nullable()->default(100)->change();
            $t->string('component', 4)->nullable()->after('type');  // ww | pt | qa | null = not graded
            $t->unsignedTinyInteger('term')->nullable()->after('component');
            $t->foreignId('assessment_id')->nullable()->after('term')->constrained()->nullOnDelete();
            $t->text('body_html')->nullable();
            $t->text('body_css')->nullable();
            $t->text('body_js')->nullable();
        });
        Schema::table('class_subjects', function (Blueprint $t) {
            $t->string('card_color', 20)->nullable();
        });
        Schema::create('class_card_prefs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('class_subject_id')->constrained()->cascadeOnDelete();
            $t->string('color', 20)->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'class_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_card_prefs');
        Schema::table('class_subjects', fn (Blueprint $t) => $t->dropColumn('card_color'));
        Schema::table('assignments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('assessment_id');
            $t->dropColumn(['component', 'term', 'body_html', 'body_css', 'body_js']);
        });
    }
};
