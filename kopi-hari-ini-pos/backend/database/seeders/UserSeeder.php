<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'      => 'Achmad Fandanish',
                'username'  => 'achmad.f',
                'password'  => Hash::make('password123', ['rounds' => 12]),
                'role'      => 'admin',
                'is_active' => true,
            ],
            [
                'name'      => 'Dewi Lestari',
                'username'  => 'kasir01',
                'password'  => Hash::make('password123', ['rounds' => 12]),
                'role'      => 'kasir',
                'is_active' => true,
            ],
            [
                'name'      => 'Bagas Saputra',
                'username'  => 'kasir02',
                'password'  => Hash::make('password123', ['rounds' => 12]),
                'role'      => 'kasir',
                'is_active' => false, // inactive as per spec
            ],
            [
                'name'      => 'Sri Wulandari',
                'username'  => 'sri.manajer',
                'password'  => Hash::make('password123', ['rounds' => 12]),
                'role'      => 'manajer',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['username' => $userData['username']],
                $userData
            );
        }
    }
}
