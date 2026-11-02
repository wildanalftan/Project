<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->date('session_date');
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['session_date', 'status']);
        });

        Schema::create('opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opname_session_id')->constrained('opname_sessions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('system_qty', 12, 3); // stok sistem saat baris dibuat
            $table->decimal('physical_qty', 12, 3)->nullable(); // hasil hitung fisik
            $table->decimal('difference', 12, 3)->nullable(); // physical - system, server-computed
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['opname_session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_items');
        Schema::dropIfExists('opname_sessions');
    }
};
