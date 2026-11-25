<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many 1-hour sessions a subject meets each week (DepEd time allotment). Null = use the built-in DepEd default.
        Schema::table('subjects', fn (Blueprint $t) => $t->unsignedTinyInteger('hours_per_week')->nullable()->after('qa_weight'));
    }

    public function down(): void
    {
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('hours_per_week'));
    }
};
