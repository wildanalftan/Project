<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 20)->unique(); // TRX-0231
            $table->char('client_uuid', 36)->unique()->nullable(); // idempotency key from kasir
            $table->foreignId('user_id')->constrained('users'); // kasir
            $table->string('table_label', 30)->nullable(); // "Meja 3"
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total');
            $table->enum('payment_method', ['tunai', 'qris', 'kartu']);
            $table->unsignedBigInteger('paid_amount');
            $table->unsignedBigInteger('change_amount');
            $table->unsignedBigInteger('total_cogs');
            $table->unsignedBigInteger('gross_profit');
            $table->enum('status', ['completed', 'void'])->default('completed');
            $table->boolean('is_offline')->default(false);
            $table->timestamp('occurred_at'); // real transaction time
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('occurred_at');
            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 120); // snapshot
            $table->decimal('qty', 12, 3);
            $table->unsignedBigInteger('unit_price');  // snapshot harga jual
            $table->unsignedBigInteger('unit_cost');   // snapshot HPP per unit
            $table->unsignedBigInteger('line_total');
            $table->unsignedBigInteger('line_cogs');
            $table->unsignedBigInteger('line_profit');
            $table->timestamps();

            $table->index('transaction_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};
