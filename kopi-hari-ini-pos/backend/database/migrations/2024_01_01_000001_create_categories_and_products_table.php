<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('sku', 30)->unique();
            $table->string('name', 120);
            $table->string('unit', 20); // pcs, kg, L
            $table->unsignedBigInteger('selling_price'); // rupiah full, no decimal
            $table->unsignedBigInteger('current_cost')->default(0); // weighted average HPP
            $table->decimal('stock_qty', 12, 3)->default(0); // decimal for kg/L support
            $table->decimal('low_stock_threshold', 12, 3)->default(10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('category_id');
            $table->index('is_active');
            // FULLTEXT index only supported on MySQL — skip for SQLite test environments
            if (config('database.default') === 'mysql') {
                $table->fullText(['name', 'sku'], 'ft_products');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
