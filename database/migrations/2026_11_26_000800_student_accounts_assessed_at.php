<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fee account can exist before tuition is set (e.g. a bookstore order or document fee comes first).
 * assessed_at marks when Finance actually set the student's fees; null = "fees not set".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_accounts', function (Blueprint $t) {
            $t->timestamp('assessed_at')->nullable()->after('net_amount');
        });
        DB::table('student_accounts')->update(['assessed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('student_accounts', function (Blueprint $t) {
            $t->dropColumn('assessed_at');
        });
    }
};
