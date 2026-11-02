<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * ProductionSeeder — hanya membuat akun admin, kategori dasar, dan pengaturan toko.
 * Tanpa data demo sama sekali.
 * Jalankan setelah DemoResetSeeder: php artisan db:seed --class=ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Settings default
        $settings = [
            'store_name'     => 'Kopi Hari Ini',
            'store_address'  => 'Bandung',
            'store_phone'    => '',
            'tax_percent'    => '10',
            'hpp_method'     => 'weighted_average',
            'receipt_footer' => 'Terima kasih telah berbelanja!',
        ];
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Admin account
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'      => 'Administrator',
                'password'  => Hash::make('ganti_password_segera', ['rounds' => 12]),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Basic categories
        $categories = [
            ['name' => 'Kopi Susu',      'sort_order' => 1],
            ['name' => 'Espresso Based', 'sort_order' => 2],
            ['name' => 'Non-Kopi',       'sort_order' => 3],
            ['name' => 'Makanan',        'sort_order' => 4],
        ];
        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }

        $this->command->info('✅ Production seed berhasil.');
        $this->command->warn('   ⚠️  PENTING: Segera ganti password akun admin (username: admin).');
    }
}
