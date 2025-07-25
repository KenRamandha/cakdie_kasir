<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create owner account
        User::create([
            'name' => 'Pemilik Toko',
            'username' => 'owner',
            'email' => 'owner@toko.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
            'is_active' => true,
        ]);

        // Create employee account
        User::create([
            'name' => 'Pegawai Kasir',
            'username' => 'kasir',
            'email' => 'kasir@toko.com',
            'password' => Hash::make('password123'),
            'role' => 'pegawai',
            'is_active' => true,
        ]);
    }
}