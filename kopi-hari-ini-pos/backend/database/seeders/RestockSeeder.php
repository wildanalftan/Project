<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Restock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class RestockSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'achmad.f')->first();

        // Historical restocks for the past 6 months
        $restocks = [
            // Biji Kopi Arabika
            ['sku' => 'KHI-B001', 'qty' => 20,    'cost' => 275000, 'supplier' => 'Petani Kopi Garut',     'months_ago' => 6],
            ['sku' => 'KHI-B001', 'qty' => 20,    'cost' => 280000, 'supplier' => 'Petani Kopi Garut',     'months_ago' => 4],
            ['sku' => 'KHI-B001', 'qty' => 15,    'cost' => 282000, 'supplier' => 'Petani Kopi Garut',     'months_ago' => 2],
            // Susu UHT
            ['sku' => 'KHI-B002', 'qty' => 48,    'cost' => 16500,  'supplier' => 'Indomaret',             'months_ago' => 5],
            ['sku' => 'KHI-B002', 'qty' => 48,    'cost' => 16800,  'supplier' => 'Indomaret',             'months_ago' => 3],
            ['sku' => 'KHI-B002', 'qty' => 36,    'cost' => 16500,  'supplier' => 'Indomaret',             'months_ago' => 1],
            // Gula Aren Cair
            ['sku' => 'KHI-B003', 'qty' => 10,    'cost' => 31000,  'supplier' => 'Supplier Bandung',      'months_ago' => 4],
            ['sku' => 'KHI-B003', 'qty' => 10,    'cost' => 32000,  'supplier' => 'Supplier Bandung',      'months_ago' => 2],
            // Matcha Latte — shows restock after out-of-stock
            ['sku' => 'KHI-0501', 'qty' => 30,    'cost' => 14200,  'supplier' => 'Matcha Supplier',       'months_ago' => 3],
            // Kopi Susu Gula Aren
            ['sku' => 'KHI-0412', 'qty' => 50,    'cost' => 11200,  'supplier' => null,                    'months_ago' => 5],
            ['sku' => 'KHI-0412', 'qty' => 50,    'cost' => 11400,  'supplier' => null,                    'months_ago' => 2],
            // Cappuccino
            ['sku' => 'KHI-0298', 'qty' => 40,    'cost' => 12900,  'supplier' => null,                    'months_ago' => 4],
            ['sku' => 'KHI-0298', 'qty' => 40,    'cost' => 13100,  'supplier' => null,                    'months_ago' => 1],
        ];

        foreach ($restocks as $r) {
            $product = Product::where('sku', $r['sku'])->first();
            if (!$product) continue;

            $receivedAt = now()->subMonths($r['months_ago'])->addDays(rand(1, 15));

            Restock::create([
                'product_id'  => $product->id,
                'user_id'     => $admin->id,
                'qty'         => $r['qty'],
                'unit_cost'   => $r['cost'],
                'supplier'    => $r['supplier'],
                'cost_before' => $product->current_cost,
                'cost_after'  => $r['cost'],
                'note'        => 'Restock stok awal demo',
                'received_at' => $receivedAt,
            ]);

            // Stock movement for this restock
            StockMovement::create([
                'product_id'     => $product->id,
                'user_id'        => $admin->id,
                'type'           => 'restock',
                'reference_type' => 'Restock',
                'reference_id'   => Restock::where('product_id', $product->id)->latest()->first()->id,
                'qty_before'     => $product->stock_qty,
                'qty_change'     => $r['qty'],
                'qty_after'      => $product->stock_qty + $r['qty'],
                'cost_before'    => $product->current_cost,
                'cost_after'     => $r['cost'],
                'note'           => 'Restock demo historis',
                'occurred_at'    => $receivedAt,
            ]);
        }
    }
}
