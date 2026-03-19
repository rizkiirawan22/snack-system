<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type'); // stock_in | sale | adjustment | void
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable(); // model class name
            $table->integer('quantity_change'); // positif = masuk, negatif = keluar
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
