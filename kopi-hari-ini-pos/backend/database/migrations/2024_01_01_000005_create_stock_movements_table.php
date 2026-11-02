<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['restock', 'sale', 'opname', 'adjustment']);
            $table->string('reference_type', 50)->nullable(); // Restock, Transaction, OpnameSession
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('qty_before', 12, 3);
            $table->decimal('qty_change', 12, 3); // negative for deductions
            $table->decimal('qty_after', 12, 3);
            $table->unsignedBigInteger('cost_before');
            $table->unsignedBigInteger('cost_after');
            $table->string('note', 255)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['product_id', 'occurred_at']);
            $table->index('type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
