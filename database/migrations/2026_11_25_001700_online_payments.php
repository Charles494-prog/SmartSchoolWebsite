<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Online payments: the school's payment channels (bank/e-wallet + QR) and proof-of-payment uploads that finance approves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_channels', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);                    // e.g. GCash, BDO
            $t->string('kind', 10)->default('gcash');  // payment method key (gcash, bank, …)
            $t->string('account_name', 120)->nullable();
            $t->string('account_number', 60)->nullable();
            $t->string('instructions', 500)->nullable();
            $t->string('qr_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('payment_proofs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_channel_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('reference', 60)->nullable();
            $t->date('paid_on');
            $t->string('screenshot_path');
            $t->string('status', 10)->default('pending');   // pending | approved | rejected
            $t->string('note', 255)->nullable();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payment_channels');
    }
};
