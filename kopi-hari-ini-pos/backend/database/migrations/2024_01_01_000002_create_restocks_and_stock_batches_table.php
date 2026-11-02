<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restocks table first because stock_batches references it
        Schema::create('restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('qty', 12, 3);
            $table->unsignedBigInteger('unit_cost'); // purchase price per unit
            $table->string('supplier', 120)->nullable();
            $table->unsignedBigInteger('cost_before'); // HPP before restock
            $table->unsignedBigInteger('cost_after');  // HPP after restock
            $table->string('note', 255)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['product_id', 'received_at']);
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('restock_id')->nullable()->constrained('restocks')->nullOnDelete();
            $table->decimal('qty_in', 12, 3);
            $table->decimal('qty_remaining', 12, 3);
            $table->unsignedBigInteger('unit_cost');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['product_id', 'received_at']);
            $table->index(['product_id', 'qty_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('restocks');
    }
};
