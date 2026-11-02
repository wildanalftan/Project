<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    // Products with selling price and HPP — loaded once for performance
    private array $products = [];
    private array $kasirIds = [];
    private float $taxPercent = 10;

    // Peak hours for realistic distribution
    private array $morningPeak = [7, 8, 9, 10, 11]; // morning rush
    private array $afternoonPeak = [14, 15, 16, 17, 18]; // afternoon rush
    private array $slowHours = [12, 13, 19, 20]; // moderate

    public function run(): void
    {
        $this->taxPercent = (float) Setting::getValue('tax_percent', 10);

        // Load active sellable products (exclude raw materials)
        $this->products = Product::where('is_active', true)
            ->where('stock_qty', '>', 0)
            ->where('selling_price', '<=', 35000) // menu items
            ->get()
            ->toArray();

        $this->kasirIds = User::where('role', 'kasir')
            ->where('is_active', true)
            ->pluck('id')
            ->toArray();

        if (empty($this->kasirIds)) {
            $this->kasirIds = [User::where('role', 'admin')->first()->id];
        }

        // Generate 6 months of transactions
        $today   = Carbon::today();
        $startOf = $today->copy()->subMonths(6)->startOfMonth();

        $invoiceCounter = [];

        // Disable stock movement for seeder (too slow for 6 months)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $allTransactions = [];
        $allItems        = [];

        for ($day = $startOf->copy(); $day->lte($today); $day->addDay()) {
            $dateStr = $day->format('Ymd');

            // Daily transaction count: 142 on today, ~80-120 on other days
            $isToday     = $day->isSameDay($today);
            $isWeekend   = $day->isWeekend();
            $txCount     = $isToday ? 142 : ($isWeekend ? rand(90, 130) : rand(60, 100));

            for ($i = 0; $i < $txCount; $i++) {
                $occurredAt = $this->randomOccurredAt($day);
                $kasirId    = $this->kasirIds[array_rand($this->kasirIds)];
                $dayKey     = $occurredAt->format('md');

                $invoiceCounter[$dayKey] = ($invoiceCounter[$dayKey] ?? 0) + 1;
                $invoiceNo = 'TRX-' . $dayKey . '-' . str_pad($invoiceCounter[$dayKey], 4, '0', STR_PAD_LEFT);

                // Pick 1-4 random items
                $itemCount    = rand(1, 4);
                $itemProducts = $this->pickRandomProducts($itemCount);

                $subtotal  = 0;
                $totalCogs = 0;
                $txItems   = [];

                foreach ($itemProducts as $prod) {
                    $qty        = rand(1, 3);
                    $lineTotal  = $prod['selling_price'] * $qty;
                    $lineCogs   = $prod['current_cost'] * $qty;
                    $lineProfit = $lineTotal - $lineCogs;

                    $subtotal  += $lineTotal;
                    $totalCogs += $lineCogs;

                    $txItems[] = [
                        'product_id'   => $prod['id'],
                        'product_name' => $prod['name'],
                        'qty'          => $qty,
                        'unit_price'   => $prod['selling_price'],
                        'unit_cost'    => $prod['current_cost'],
                        'line_total'   => $lineTotal,
                        'line_cogs'    => $lineCogs,
                        'line_profit'  => $lineProfit,
                        'created_at'   => $occurredAt,
                        'updated_at'   => $occurredAt,
                    ];
                }

                $taxAmount   = (int) round($subtotal * $this->taxPercent / 100);
                $total       = $subtotal + $taxAmount;
                $grossProfit = $total - $totalCogs;
                $payMethods  = ['tunai', 'qris', 'kartu'];
                $method      = $payMethods[array_rand($payMethods)];
                $paidAmount  = $method === 'tunai'
                    ? (int) (ceil($total / 5000) * 5000)
                    : $total;

                $allTransactions[] = [
                    'invoice_no'     => $invoiceNo,
                    'client_uuid'    => null,
                    'user_id'        => $kasirId,
                    'table_label'    => rand(0, 1) ? 'Meja ' . rand(1, 10) : null,
                    'subtotal'       => $subtotal,
                    'tax_amount'     => $taxAmount,
                    'total'          => $total,
                    'payment_method' => $method,
                    'paid_amount'    => $paidAmount,
                    'change_amount'  => max(0, $paidAmount - $total),
                    'total_cogs'     => $totalCogs,
                    'gross_profit'   => $grossProfit,
                    'status'         => 'completed',
                    'is_offline'     => false,
                    'occurred_at'    => $occurredAt,
                    'synced_at'      => null,
                    'created_at'     => $occurredAt,
                    'updated_at'     => $occurredAt,
                ];

                // Store items with placeholder for transaction_id
                $allItems[] = $txItems;
            }
        }

        // Batch insert transactions
        $chunks = array_chunk($allTransactions, 500);
        foreach ($chunks as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        // Fetch inserted transaction IDs (in order)
        $insertedIds = DB::table('transactions')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        // Attach items to transactions
        $flatItems = [];
        foreach ($insertedIds as $idx => $txId) {
            if (!isset($allItems[$idx])) continue;
            foreach ($allItems[$idx] as $item) {
                $item['transaction_id'] = $txId;
                $flatItems[] = $item;
            }
        }

        $itemChunks = array_chunk($flatItems, 1000);
        foreach ($itemChunks as $chunk) {
            DB::table('transaction_items')->insert($chunk);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Write sample stock movements for today's sales
        $this->seedTodayStockMovements();
    }

    private function randomOccurredAt(Carbon $day): Carbon
    {
        $rand = rand(0, 100);
        if ($rand < 40) {
            $hour = $this->morningPeak[array_rand($this->morningPeak)];
        } elseif ($rand < 75) {
            $hour = $this->afternoonPeak[array_rand($this->afternoonPeak)];
        } else {
            $hour = $this->slowHours[array_rand($this->slowHours)];
        }

        return $day->copy()->setHour($hour)->setMinute(rand(0, 59))->setSecond(rand(0, 59));
    }

    private function pickRandomProducts(int $count): array
    {
        if (empty($this->products)) return [];
        $keys   = array_rand($this->products, min($count, count($this->products)));
        $keys   = is_array($keys) ? $keys : [$keys];
        return array_map(fn($k) => $this->products[$k], $keys);
    }

    private function seedTodayStockMovements(): void
    {
        // Write ~214 stock_movements records for today's transactions
        $todayTxs = Transaction::where('occurred_at', '>=', Carbon::today())
            ->with('items.product')
            ->limit(50)
            ->get();

        $kasirId = $this->kasirIds[0];

        foreach ($todayTxs as $tx) {
            foreach ($tx->items as $item) {
                $product = $item->product;
                if (!$product) continue;

                StockMovement::create([
                    'product_id'     => $product->id,
                    'user_id'        => $kasirId,
                    'type'           => 'sale',
                    'reference_type' => 'Transaction',
                    'reference_id'   => $tx->id,
                    'qty_before'     => $product->stock_qty + $item->qty,
                    'qty_change'     => -$item->qty,
                    'qty_after'      => $product->stock_qty,
                    'cost_before'    => $item->unit_cost,
                    'cost_after'     => $item->unit_cost,
                    'note'           => "Penjualan struk {$tx->invoice_no}",
                    'occurred_at'    => $tx->occurred_at,
                ]);
            }
        }
    }
}
