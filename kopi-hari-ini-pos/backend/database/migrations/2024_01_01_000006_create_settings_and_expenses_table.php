<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('operating_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('period'); // first day of month: 2024-11-01
            $table->string('category', 100); // Gaji, Sewa, Listrik-Air-Internet, etc.
            $table->unsignedBigInteger('amount'); // rupiah full
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expenses');
        Schema::dropIfExists('settings');
    }
};
