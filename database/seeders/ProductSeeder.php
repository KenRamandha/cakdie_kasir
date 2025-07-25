<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Ambil user pertama
        $user = User::first();

        if (!$user) {
            $this->command->error('Tidak ada user ditemukan. Jalankan UserSeeder terlebih dahulu.');
            return;
        }

        // Cari atau buat kategori "Screen Printing"
        $category = Category::firstOrCreate(
            ['code' => 'SCR'],
            [
                'name' => 'Screen Printing',
                'description' => 'Produk dan layanan sablon',
                'is_active' => true,
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]
        );

        $products = [
            [
                'code' => 'SCR-TSHIRT',
                'name' => 'Sablon Kaos',
                'description' => 'Jasa sablon kaos custom.',
                'price' => 75000,
                'cost_price' => 50000,
                'stock' => 100,
                'min_stock' => 10,
                'unit' => 'pcs',
            ],
            [
                'code' => 'SCR-TOTE',
                'name' => 'Sablon Tote Bag',
                'description' => 'Jasa sablon tote bag ramah lingkungan.',
                'price' => 50000,
                'cost_price' => 30000,
                'stock' => 80,
                'min_stock' => 10,
                'unit' => 'pcs',
            ],
            [
                'code' => 'SCR-INK',
                'name' => 'Tinta Sablon Tekstil',
                'description' => 'Tinta sablon khusus untuk kain.',
                'price' => 120000,
                'cost_price' => 80000,
                'stock' => 50,
                'min_stock' => 5,
                'unit' => 'botol',
            ],
        ];

        foreach ($products as $product) {
            Product::create([
                ...$product,
                'category_id' => $category->id,
                'is_active' => true,
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]);
        }
    }
}
