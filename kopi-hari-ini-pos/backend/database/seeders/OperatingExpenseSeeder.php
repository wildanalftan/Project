<?php

namespace Database\Seeders;

use App\Models\OperatingExpense;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OperatingExpenseSeeder extends Seeder
{
    public function run(): void
    {
        // Last 6 months of operating expenses
        for ($i = 0; $i <= 5; $i++) {
            $period = Carbon::now()->subMonths($i)->startOfMonth()->toDateString();

            $expenses = [
                ['category' => 'Gaji dan Upah',          'amount' => 14500000],
                ['category' => 'Sewa Tempat',             'amount' => 6000000],
                ['category' => 'Listrik, Air, Internet',  'amount' => 2150000],
                ['category' => 'Hosting dan Domain',      'amount' => 208000],
                ['category' => 'Perlengkapan Operasional','amount' => rand(300000, 800000)],
                ['category' => 'Bahan Habis Pakai',       'amount' => rand(200000, 500000)],
            ];

            foreach ($expenses as $exp) {
                OperatingExpense::create([
                    'period'   => $period,
                    'category' => $exp['category'],
                    'amount'   => $exp['amount'],
                    'note'     => null,
                ]);
            }
        }
    }
}
