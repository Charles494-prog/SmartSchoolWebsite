<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookstore: items posted by the admin/finance office (books, uniforms, merch, souvenirs), orders by families,
 * extra charges on the student's account (so tuition and orders show separately), and how each payment is split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('category', 20)->default('merch');     // book | uniform | merch | souvenir | other
            $t->text('description')->nullable();
            $t->decimal('price', 10, 2);
            $t->json('sizes')->nullable();                      // e.g. ["S","M","L"]
            $t->unsignedInteger('stock')->nullable();           // null = no limit
            $t->string('image_path')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('shop_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('total', 10, 2);
            $t->string('status', 12)->default('placed');       // placed | ready | claimed | cancelled
            $t->timestamps();
        });
        Schema::create('shop_order_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shop_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shop_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name', 120);
            $t->string('size', 20)->nullable();
            $t->unsignedSmallInteger('qty');
            $t->decimal('price', 10, 2);
        });
        Schema::create('account_charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('student_account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('shop_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description', 160);
            $t->decimal('amount', 10, 2);
            $t->boolean('is_void')->default(false);
            $t->timestamps();
        });
        Schema::create('payment_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_charge_id')->nullable()->constrained()->cascadeOnDelete();   // null = tuition & fees
            $t->decimal('amount', 10, 2);
        });
        Schema::table('payment_proofs', fn (Blueprint $t) => $t->string('pay_key', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('payment_proofs', fn (Blueprint $t) => $t->dropColumn('pay_key'));
        foreach (['payment_allocations', 'account_charges', 'shop_order_lines', 'shop_orders', 'shop_items'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
