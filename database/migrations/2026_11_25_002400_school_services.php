<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Documents and services families can request from the registrar (TOR, good moral, diploma copy…), with a fee. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_services', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('description', 255)->nullable();
            $t->decimal('fee', 10, 2)->default(0);
            $t->unsignedSmallInteger('days')->default(3);           // processing days
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('service_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_service_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('account_charge_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('copies')->default(1);
            $t->string('purpose', 255)->nullable();
            $t->string('status', 12)->default('requested');   // requested | processing | released | declined
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('school_services');
    }
};
