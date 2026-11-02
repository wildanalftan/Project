<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Kopi Susu',      'sort_order' => 1],
            ['name' => 'Espresso Based', 'sort_order' => 2],
            ['name' => 'Non-Kopi',       'sort_order' => 3],
            ['name' => 'Makanan',        'sort_order' => 4],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
