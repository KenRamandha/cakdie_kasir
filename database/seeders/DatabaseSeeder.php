<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Create default owner user
        $owner = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@pos.com',
            'password' => Hash::make('password'),
            'role' => 'pemilik',
            'is_active' => true,
        ]);

        // Create default employee user
        $employee = User::create([
            'name' => 'Kasir 1',
            'username' => 'kasir1',
            'email' => 'kasir1@pos.com',
            'password' => Hash::make('password'),
            'role' => 'pegawai',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        // Create default categories
        $categories = [
            [
                'name' => 'Makanan',
                'description' => 'Produk makanan dan snack',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Minuman',
                'description' => 'Berbagai jenis minuman',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Elektronik',
                'description' => 'Peralatan elektronik',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'ATK',
                'description' => 'Alat Tulis Kantor',
                'created_by' => $owner->id,
            ],
        ];

        foreach ($categories as $categoryData) {
            Category::create($categoryData);
        }

        // Get created categories
        $makananCategory = Category::where('name', 'Makanan')->first();
        $minumanCategory = Category::where('name', 'Minuman')->first();
        $elektronikCategory = Category::where('name', 'Elektronik')->first();
        $atkCategory = Category::where('name', 'ATK')->first();

        // Create sample products
        $products = [
            // Makanan
            [
                'name' => 'Nasi Goreng',
                'description' => 'Nasi goreng spesial dengan telur',
                'category_id' => $makananCategory->id,
                'price' => 15000,
                'cost_price' => 10000,
                'stock' => 50,
                'min_stock' => 10,
                'unit' => 'porsi',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Mie Ayam',
                'description' => 'Mie ayam dengan topping lengkap',
                'category_id' => $makananCategory->id,
                'price' => 12000,
                'cost_price' => 8000,
                'stock' => 30,
                'min_stock' => 5,
                'unit' => 'porsi',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Kerupuk',
                'description' => 'Kerupuk udang crispy',
                'category_id' => $makananCategory->id,
                'price' => 5000,
                'cost_price' => 3000,
                'stock' => 100,
                'min_stock' => 20,
                'unit' => 'bungkus',
                'created_by' => $owner->id,
            ],
            
            // Minuman
            [
                'name' => 'Es Teh Manis',
                'description' => 'Es teh manis segar',
                'category_id' => $minumanCategory->id,
                'price' => 3000,
                'cost_price' => 1500,
                'stock' => 80,
                'min_stock' => 15,
                'unit' => 'gelas',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Kopi Hitam',
                'description' => 'Kopi hitam tubruk',
                'category_id' => $minumanCategory->id,
                'price' => 5000,
                'cost_price' => 2500,
                'stock' => 60,
                'min_stock' => 10,
                'unit' => 'gelas',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Air Mineral',
                'description' => 'Air mineral botol 600ml',
                'category_id' => $minumanCategory->id,
                'price' => 3000,
                'cost_price' => 2000,
                'stock' => 200,
                'min_stock' => 50,
                'unit' => 'botol',
                'created_by' => $owner->id,
            ],
            
            // Elektronik
            [
                'name' => 'Kabel USB',
                'description' => 'Kabel USB Type-C 1 meter',
                'category_id' => $elektronikCategory->id,
                'price' => 25000,
                'cost_price' => 15000,
                'stock' => 25,
                'min_stock' => 5,
                'unit' => 'pcs',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Power Bank',
                'description' => 'Power bank 10000mAh',
                'category_id' => $elektronikCategory->id,
                'price' => 150000,
                'cost_price' => 120000,
                'stock' => 15,
                'min_stock' => 3,
                'unit' => 'pcs',
                'created_by' => $owner->id,
            ],
            
            // ATK
            [
                'name' => 'Pulpen',
                'description' => 'Pulpen tinta biru',
                'category_id' => $atkCategory->id,
                'price' => 2000,
                'cost_price' => 1200,
                'stock' => 150,
                'min_stock' => 30,
                'unit' => 'pcs',
                'created_by' => $owner->id,
            ],
            [
                'name' => 'Buku Tulis',
                'description' => 'Buku tulis 38 lembar',
                'category_id' => $atkCategory->id,
                'price' => 5000,
                'cost_price' => 3500,
                'stock' => 75,
                'min_stock' => 15,
                'unit' => 'pcs',
                'created_by' => $owner->id,
            ],
        ];

        foreach ($products as $productData) {
            Product::create($productData);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Default users created:');
        $this->command->info('Owner - Username: admin, Password: password');
        $this->command->info('Employee - Username: kasir1, Password: password');
    }
}