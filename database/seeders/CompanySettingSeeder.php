<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySettingSeeder extends Seeder
{
    public function run()
    {
        DB::table('company_settings')->insert([
            'name' => 'Nama Perusahaan Anda',
            'address' => 'Alamat lengkap perusahaan',
            'phone' => '081234567890',
            'email' => 'info@perusahaan.com',
            'website' => 'https://www.perusahaan.com',
            'tax_id' => '123456789012345',
            'logo_path' => null, 
            'receipt_footer' => 'Terima kasih telah berbelanja di toko kami!',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}