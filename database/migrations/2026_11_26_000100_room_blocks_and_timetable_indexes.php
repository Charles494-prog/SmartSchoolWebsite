<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $t) {
            $t->foreignId('grade_level_id')->nullable()->after('building')->constrained()->nullOnDelete();
        });
        // Conflict checks look slots up by day + time, by room, and by class.
        Schema::table('schedule_slots', function (Blueprint $t) {
            $t->index(['day_of_week', 'starts_at', 'ends_at'], 'slots_day_time_idx');
            $t->index(['room', 'day_of_week'], 'slots_room_day_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $t) {
            $t->dropIndex('slots_day_time_idx');
            $t->dropIndex('slots_room_day_idx');
        });
        Schema::table('rooms', fn (Blueprint $t) => $t->dropConstrainedForeignId('grade_level_id'));
    }
};
