<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'user_id' => 'USR-0001',
            'name' => 'Pemilik Toko',
            'username' => 'owner',
            'email' => 'owner@toko.com',
            'password' => Hash::make('password123'),
            'role' => 'pemilik',
            'is_active' => true,
        ]);
        User::create([
            'user_id' => 'USR-0002',
            'name' => 'Pegawai Kasir',
            'username' => 'kasir',
            'email' => 'kasir@toko.com',
            'password' => Hash::make('password123'),
            'role' => 'pegawai',
            'is_active' => true,
        ]);
    }
}