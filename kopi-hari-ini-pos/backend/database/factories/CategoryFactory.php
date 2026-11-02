<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->unique()->word() . ' Kategori',
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
