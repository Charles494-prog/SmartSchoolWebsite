<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $t) {
            // When the admission/registration form opens (before classes start). starts_on = first day of classes.
            $t->date('enrollment_opens_on')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('school_years', fn (Blueprint $t) => $t->dropColumn('enrollment_opens_on'));
    }
};
