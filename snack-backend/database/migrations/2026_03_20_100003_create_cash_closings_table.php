<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();     // kasir yang menutup
            $table->date('closing_date');
            $table->decimal('opening_balance', 15, 2)->default(0);             // saldo kas awal shift
            $table->decimal('cash_sales', 15, 2)->default(0);                  // total penjualan tunai sistem
            $table->decimal('transfer_sales', 15, 2)->default(0);              // total transfer sistem
            $table->decimal('qris_sales', 15, 2)->default(0);                  // total qris sistem
            $table->decimal('total_expected', 15, 2)->default(0);              // expected = opening + cash_sales
            $table->decimal('actual_cash', 15, 2)->default(0);                 // uang fisik dihitung kasir
            $table->decimal('difference', 15, 2)->default(0);                  // actual - expected (positif = lebih, negatif = kurang)
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);               // semua metode pembayaran
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
