<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a payment is for: down payment, an installment, the full balance, one fee, or any amount. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->string('purpose', 120)->nullable());
        Schema::table('payment_proofs', fn (Blueprint $t) => $t->string('purpose', 120)->nullable());
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn('purpose'));
        Schema::table('payment_proofs', fn (Blueprint $t) => $t->dropColumn('purpose'));
    }
};
