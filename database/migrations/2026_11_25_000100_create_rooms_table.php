<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40)->unique();
            $t->string('building', 80)->nullable();
            $t->unsignedSmallInteger('capacity')->default(40);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Turn rooms already typed into sections/schedules into real rooms.
        $names = DB::table('sections')->whereNotNull('room')->where('room', '!=', '')->pluck('room')
            ->merge(DB::table('schedule_slots')->whereNotNull('room')->where('room', '!=', '')->pluck('room'))
            ->map(fn ($n) => trim($n))->unique()->filter();
        foreach ($names as $n) {
            $cap = (int) (DB::table('sections')->where('room', $n)->max('capacity') ?: 40);
            DB::table('rooms')->insert(['name' => $n, 'capacity' => $cap, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
