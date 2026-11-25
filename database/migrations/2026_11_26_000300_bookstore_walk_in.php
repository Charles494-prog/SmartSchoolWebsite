<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Walk-in orders: bookstore staff shop for a student at the counter; no approve/prepare steps.
        Schema::table('shop_orders', function (Blueprint $t) {
            $t->boolean('walk_in')->default(false)->after('status');
            $t->index(['status', 'walk_in']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $t) {
            $t->dropIndex(['status', 'walk_in']);
            $t->dropColumn('walk_in');
        });
    }
};
