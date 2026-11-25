<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra details when lending a book: how many copies, its condition and a note. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_loans', function (Blueprint $t) {
            $t->unsignedTinyInteger('copies')->default(1);
            $t->string('condition', 20)->nullable();
            $t->string('note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('book_loans', fn (Blueprint $t) => $t->dropColumn(['copies', 'condition', 'note']));
    }
};
