<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\Restock;
use App\Models\Setting;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\HppService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            RestockSeeder::class,
            TransactionSeeder::class,
            OperatingExpenseSeeder::class,
        ]);
    }
}
