<?php

namespace Database\Seeders;

use App\Models\OperatingExpense;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\StockMovement;
use App\Models\Restock;
use App\Models\StockBatch;
use App\Models\OpnameItem;
use App\Models\OpnameSession;
use Illuminate\Database\Seeder;

/**
 * DemoResetSeeder — hapus seluruh data demo sebelum serah terima ke klien.
 * Jalankan: php artisan db:seed --class=DemoResetSeeder
 *
 * Peringatan: tindakan ini tidak bisa dibatalkan!
 */
class DemoResetSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('⚠️  Menghapus semua data demo...');

        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');

        TransactionItem::truncate();
        Transaction::truncate();
        StockMovement::truncate();
        StockBatch::truncate();
        Restock::truncate();
        OpnameItem::truncate();
        OpnameSession::truncate();
        OperatingExpense::truncate();

        // Reset stock quantities to zero
        \App\Models\Product::query()->update(['stock_qty' => 0, 'current_cost' => 0]);

        // Reset personal_access_tokens (force re-login)
        \Illuminate\Support\Facades\DB::table('personal_access_tokens')->truncate();

        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('✅ Data demo berhasil dihapus.');
        $this->command->info('   Jalankan ProductionSeeder untuk mengisi data awal produksi.');
    }
}
