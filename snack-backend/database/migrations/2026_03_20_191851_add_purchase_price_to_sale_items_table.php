<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Harga beli rata-rata (AVCO) saat transaksi — snapshot agar laporan profit akurat secara historis
            $table->decimal('purchase_price', 12, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
