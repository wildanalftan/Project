<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => $this->faker->name(),
            'username' => $this->faker->unique()->userName(),
            'password' => Hash::make('password'),
            'role'     => $this->faker->randomElement(['admin', 'kasir', 'manajer']),
            'is_active'=> true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function kasir(): static
    {
        return $this->state(['role' => 'kasir']);
    }

    public function manajer(): static
    {
        return $this->state(['role' => 'manajer']);
    }
}
