<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plans', function (Blueprint $t) {
            $t->id();
            $t->string('name', 60);
            $t->unsignedTinyInteger('installments')->default(1);
            $t->unsignedTinyInteger('months_between')->default(1);
            $t->decimal('downpayment_percent', 5, 2)->default(0); // first payment as % of total (0 = equal split)
            $t->decimal('surcharge_percent', 5, 2)->default(0);   // extra charge for paying in installments
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        $now = now();
        $plans = [
            ['full', 'Full payment', 1, 0], ['semestral', 'Semestral (2 payments)', 2, 5], ['quarterly', 'Quarterly (4 payments)', 4, 2], ['monthly', 'Monthly (10 payments)', 10, 1],
        ];
        $map = [];
        foreach ($plans as [$key, $name, $n, $gap]) {
            $map[$key] = DB::table('payment_plans')->insertGetId(['name' => $name, 'installments' => $n, 'months_between' => $gap, 'created_at' => $now, 'updated_at' => $now]);
        }
        // existing accounts used text keys – point them at the new plans
        foreach ($map as $key => $id) {
            DB::table('student_accounts')->where('payment_plan', $key)->update(['payment_plan' => (string) $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
