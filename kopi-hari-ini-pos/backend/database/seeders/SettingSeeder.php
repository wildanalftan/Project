<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'store_name'     => 'Kopi Hari Ini',
            'store_address'  => 'Jl. Merdeka No. 12, Bandung',
            'store_phone'    => '0812-3456-7890',
            'tax_percent'    => '10',
            'hpp_method'     => 'weighted_average',
            'receipt_footer' => 'Terima kasih telah berbelanja!',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
