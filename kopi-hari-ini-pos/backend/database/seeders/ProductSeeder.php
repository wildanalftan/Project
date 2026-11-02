<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockBatch;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $kopiSusu    = Category::where('name', 'Kopi Susu')->first();
        $espresso    = Category::where('name', 'Espresso Based')->first();
        $nonKopi     = Category::where('name', 'Non-Kopi')->first();
        $makanan     = Category::where('name', 'Makanan')->first();

        // ─── 5 wajib dari spesifikasi ─────────────────────────────────────────
        $mandatory = [
            [
                'category_id'  => $kopiSusu->id,
                'sku'          => 'KHI-0412',
                'name'         => 'Kopi Susu Gula Aren',
                'unit'         => 'pcs',
                'selling_price'=> 22000,
                'current_cost' => 11400,
                'stock_qty'    => 48,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
            [
                'category_id'  => $espresso->id,
                'sku'          => 'KHI-0298',
                'name'         => 'Cappuccino',
                'unit'         => 'pcs',
                'selling_price'=> 25000,
                'current_cost' => 13100,
                'stock_qty'    => 31,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
            [
                'category_id'  => $kopiSusu->id,
                'sku'          => 'KHI-0105',
                'name'         => 'Kopi Hitam Tubruk',
                'unit'         => 'pcs',
                'selling_price'=> 15000,
                'current_cost' => 7200,
                'stock_qty'    => 8,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
            [
                'category_id'  => $nonKopi->id,
                'sku'          => 'KHI-0501',
                'name'         => 'Matcha Latte',
                'unit'         => 'pcs',
                'selling_price'=> 26000,
                'current_cost' => 14800,
                'stock_qty'    => 0,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
            [
                'category_id'  => $makanan->id,
                'sku'          => 'KHI-0633',
                'name'         => 'Croissant Cokelat',
                'unit'         => 'pcs',
                'selling_price'=> 18000,
                'current_cost' => 9600,
                'stock_qty'    => 22,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
        ];

        // ─── Bahan baku ───────────────────────────────────────────────────────
        $rawMaterials = [
            [
                'category_id'  => $kopiSusu->id,
                'sku'          => 'KHI-B001',
                'name'         => 'Biji Kopi Arabika',
                'unit'         => 'kg',
                'selling_price'=> 350000,
                'current_cost' => 280000,
                'stock_qty'    => 15.5,
                'low_stock_threshold' => 5,
                'is_active'    => true,
            ],
            [
                'category_id'  => $kopiSusu->id,
                'sku'          => 'KHI-B002',
                'name'         => 'Susu UHT 1L',
                'unit'         => 'pcs',
                'selling_price'=> 22000,
                'current_cost' => 16500,
                'stock_qty'    => 24,
                'low_stock_threshold' => 10,
                'is_active'    => true,
            ],
            [
                'category_id'  => $kopiSusu->id,
                'sku'          => 'KHI-B003',
                'name'         => 'Gula Aren Cair',
                'unit'         => 'L',
                'selling_price'=> 45000,
                'current_cost' => 32000,
                'stock_qty'    => 8.5,
                'low_stock_threshold' => 3,
                'is_active'    => true,
            ],
        ];

        // ─── 40 produk tambahan untuk total 48 SKU ────────────────────────────
        // Komposisi: 39 aman, 6 menipis, 3 habis (sudah ada 1 habis di mandatory)
        $extraProducts = [
            // Kopi Susu (aman)
            ['sku' => 'KHI-0413', 'name' => 'Kopi Susu Klasik',    'cat' => $kopiSusu->id,  'sell' => 20000, 'cost' => 10000, 'stock' => 35, 'threshold' => 10],
            ['sku' => 'KHI-0414', 'name' => 'Es Kopi Susu',        'cat' => $kopiSusu->id,  'sell' => 22000, 'cost' => 11500, 'stock' => 40, 'threshold' => 10],
            ['sku' => 'KHI-0415', 'name' => 'Kopi Susu Hazelnut',  'cat' => $kopiSusu->id,  'sell' => 24000, 'cost' => 12000, 'stock' => 28, 'threshold' => 10],
            ['sku' => 'KHI-0416', 'name' => 'Kopi Susu Vanilla',   'cat' => $kopiSusu->id,  'sell' => 24000, 'cost' => 12200, 'stock' => 33, 'threshold' => 10],
            ['sku' => 'KHI-0417', 'name' => 'Dirty Coffee',        'cat' => $kopiSusu->id,  'sell' => 26000, 'cost' => 13500, 'stock' => 21, 'threshold' => 10],
            // Espresso (aman)
            ['sku' => 'KHI-0299', 'name' => 'Americano',           'cat' => $espresso->id,  'sell' => 20000, 'cost' => 9500,  'stock' => 45, 'threshold' => 10],
            ['sku' => 'KHI-0300', 'name' => 'Latte',               'cat' => $espresso->id,  'sell' => 23000, 'cost' => 12000, 'stock' => 38, 'threshold' => 10],
            ['sku' => 'KHI-0301', 'name' => 'Flat White',          'cat' => $espresso->id,  'sell' => 25000, 'cost' => 13000, 'stock' => 29, 'threshold' => 10],
            ['sku' => 'KHI-0302', 'name' => 'Espresso Shot',       'cat' => $espresso->id,  'sell' => 18000, 'cost' => 8000,  'stock' => 50, 'threshold' => 15],
            ['sku' => 'KHI-0303', 'name' => 'Macchiato',           'cat' => $espresso->id,  'sell' => 22000, 'cost' => 11000, 'stock' => 32, 'threshold' => 10],
            ['sku' => 'KHI-0304', 'name' => 'Cortado',             'cat' => $espresso->id,  'sell' => 23000, 'cost' => 11500, 'stock' => 27, 'threshold' => 10],
            ['sku' => 'KHI-0305', 'name' => 'Cold Brew',           'cat' => $espresso->id,  'sell' => 28000, 'cost' => 14000, 'stock' => 18, 'threshold' => 10],
            // Non-Kopi (aman + menipis)
            ['sku' => 'KHI-0502', 'name' => 'Cokelat Panas',       'cat' => $nonKopi->id,   'sell' => 22000, 'cost' => 11000, 'stock' => 36, 'threshold' => 10],
            ['sku' => 'KHI-0503', 'name' => 'Teh Tarik',           'cat' => $nonKopi->id,   'sell' => 18000, 'cost' => 8500,  'stock' => 42, 'threshold' => 10],
            ['sku' => 'KHI-0504', 'name' => 'Taro Latte',          'cat' => $nonKopi->id,   'sell' => 25000, 'cost' => 12500, 'stock' => 30, 'threshold' => 10],
            ['sku' => 'KHI-0505', 'name' => 'Red Velvet Latte',    'cat' => $nonKopi->id,   'sell' => 26000, 'cost' => 13000, 'stock' => 7,  'threshold' => 10], // menipis
            ['sku' => 'KHI-0506', 'name' => 'Brown Sugar Milk Tea','cat' => $nonKopi->id,   'sell' => 24000, 'cost' => 12000, 'stock' => 5,  'threshold' => 10], // menipis
            ['sku' => 'KHI-0507', 'name' => 'Lychee Tea',          'cat' => $nonKopi->id,   'sell' => 20000, 'cost' => 9500,  'stock' => 38, 'threshold' => 10],
            ['sku' => 'KHI-0508', 'name' => 'Jasmine Green Tea',   'cat' => $nonKopi->id,   'sell' => 19000, 'cost' => 9000,  'stock' => 44, 'threshold' => 10],
            ['sku' => 'KHI-0509', 'name' => 'Lemon Squash',        'cat' => $nonKopi->id,   'sell' => 18000, 'cost' => 8000,  'stock' => 33, 'threshold' => 10],
            ['sku' => 'KHI-0510', 'name' => 'Strawberry Milk',     'cat' => $nonKopi->id,   'sell' => 22000, 'cost' => 11000, 'stock' => 8,  'threshold' => 10], // menipis
            ['sku' => 'KHI-0511', 'name' => 'Kopi Susu Ubi Ungu',  'cat' => $nonKopi->id,   'sell' => 27000, 'cost' => 14000, 'stock' => 0,  'threshold' => 10], // habis
            ['sku' => 'KHI-0512', 'name' => 'Avocado Cream',       'cat' => $nonKopi->id,   'sell' => 30000, 'cost' => 15500, 'stock' => 26, 'threshold' => 10],
            // Makanan (aman + menipis + habis)
            ['sku' => 'KHI-0634', 'name' => 'Sandwich Tuna',       'cat' => $makanan->id,   'sell' => 22000, 'cost' => 13000, 'stock' => 15, 'threshold' => 10],
            ['sku' => 'KHI-0635', 'name' => 'Banana Bread',        'cat' => $makanan->id,   'sell' => 20000, 'cost' => 11000, 'stock' => 18, 'threshold' => 10],
            ['sku' => 'KHI-0636', 'name' => 'Cheesecake Slice',    'cat' => $makanan->id,   'sell' => 28000, 'cost' => 16000, 'stock' => 12, 'threshold' => 10],
            ['sku' => 'KHI-0637', 'name' => 'Roti Bakar Keju',     'cat' => $makanan->id,   'sell' => 18000, 'cost' => 9000,  'stock' => 22, 'threshold' => 10],
            ['sku' => 'KHI-0638', 'name' => 'Waffle Pandan',       'cat' => $makanan->id,   'sell' => 25000, 'cost' => 13500, 'stock' => 9,  'threshold' => 10], // menipis
            ['sku' => 'KHI-0639', 'name' => 'French Toast',        'cat' => $makanan->id,   'sell' => 24000, 'cost' => 12500, 'stock' => 7,  'threshold' => 10], // menipis
            ['sku' => 'KHI-0640', 'name' => 'Risoles Mayo',        'cat' => $makanan->id,   'sell' => 12000, 'cost' => 6500,  'stock' => 30, 'threshold' => 10],
            ['sku' => 'KHI-0641', 'name' => 'Pisang Goreng Keju',  'cat' => $makanan->id,   'sell' => 15000, 'cost' => 7500,  'stock' => 25, 'threshold' => 10],
            ['sku' => 'KHI-0642', 'name' => 'Cake Tiramisu',       'cat' => $makanan->id,   'sell' => 32000, 'cost' => 18000, 'stock' => 0,  'threshold' => 10], // habis
            ['sku' => 'KHI-0643', 'name' => 'Kue Lapis',           'cat' => $makanan->id,   'sell' => 16000, 'cost' => 8500,  'stock' => 14, 'threshold' => 10],
            ['sku' => 'KHI-0644', 'name' => 'Bubur Ayam',          'cat' => $makanan->id,   'sell' => 22000, 'cost' => 12000, 'stock' => 20, 'threshold' => 10],
            ['sku' => 'KHI-0645', 'name' => 'Nasi Goreng Spesial', 'cat' => $makanan->id,   'sell' => 35000, 'cost' => 20000, 'stock' => 16, 'threshold' => 10],
        ];

        // Insert mandatory products
        foreach ($mandatory as $data) {
            $this->createProduct($data);
        }

        // Insert raw materials
        foreach ($rawMaterials as $data) {
            $this->createProduct($data);
        }

        // Insert extra products
        foreach ($extraProducts as $data) {
            $this->createProduct([
                'category_id'         => $data['cat'],
                'sku'                 => $data['sku'],
                'name'                => $data['name'],
                'unit'                => 'pcs',
                'selling_price'       => $data['sell'],
                'current_cost'        => $data['cost'],
                'stock_qty'           => $data['stock'],
                'low_stock_threshold' => $data['threshold'],
                'is_active'           => true,
            ]);
        }
    }

    private function createProduct(array $data): void
    {
        $product = Product::updateOrCreate(
            ['sku' => $data['sku']],
            [
                'category_id'         => $data['category_id'],
                'name'                => $data['name'],
                'unit'                => $data['unit'],
                'selling_price'       => $data['selling_price'],
                'low_stock_threshold' => $data['low_stock_threshold'],
                'is_active'           => $data['is_active'] ?? true,
            ]
        );

        // Update guarded fields directly
        Product::where('id', $product->id)->update([
            'current_cost' => $data['current_cost'],
            'stock_qty'    => $data['stock_qty'],
        ]);

        // Create initial stock batch for FIFO tracking
        if ($data['stock_qty'] > 0) {
            StockBatch::updateOrCreate(
                ['product_id' => $product->id, 'restock_id' => null],
                [
                    'qty_in'       => $data['stock_qty'],
                    'qty_remaining'=> $data['stock_qty'],
                    'unit_cost'    => $data['current_cost'],
                    'received_at'  => now()->subMonths(6),
                ]
            );
        }
    }
}
