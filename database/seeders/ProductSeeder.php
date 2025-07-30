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
        $user = User::where('user_id', 'USR-0001')->first();

        if (!$user) {
            $this->command->error('User dengan user_id USR-0001 tidak ditemukan.');
            return;
        }

        $categories = Category::all()->keyBy('code');

        $products = [
            [
                'code' => 'SCR-TSHIRT-001',
                'name' => 'Sablon Kaos Cotton Combed',
                'description' => 'Jasa sablon kaos cotton combed dengan kualitas premium',
                'category_code' => 'SCR',
                'price' => 75000,
                'cost_price' => 50000,
                'stock' => 100,
                'min_stock' => 10,
                'unit' => 'pcs',
            ],
            [
                'code' => 'SCR-TOTE-001',
                'name' => 'Sablon Tote Bag Canvas',
                'description' => 'Jasa sablon tote bag canvas ramah lingkungan',
                'category_code' => 'SCR',
                'price' => 50000,
                'cost_price' => 30000,
                'stock' => 80,
                'min_stock' => 15,
                'unit' => 'pcs',
            ],
            [
                'code' => 'SCR-INK-001',
                'name' => 'Tinta Sablon Tekstil Putih',
                'description' => 'Tinta sablon khusus untuk kain warna putih',
                'category_code' => 'SCR',
                'price' => 120000,
                'cost_price' => 80000,
                'stock' => 25,
                'min_stock' => 5,
                'unit' => 'botol',
            ],
            [
                'code' => 'SCR-INK-002',
                'name' => 'Tinta Sablon Tekstil Hitam',
                'description' => 'Tinta sablon khusus untuk kain warna hitam',
                'category_code' => 'SCR',
                'price' => 120000,
                'cost_price' => 80000,
                'stock' => 8,
                'min_stock' => 10,
                'unit' => 'botol',
            ],
            
            [
                'code' => 'DTF-FILM-001',
                'name' => 'DTF Film A4',
                'description' => 'Film DTF ukuran A4 untuk printing',
                'category_code' => 'DTF',
                'price' => 15000,
                'cost_price' => 10000,
                'stock' => 200,
                'min_stock' => 50,
                'unit' => 'lembar',
            ],
            [
                'code' => 'DTF-POWDER-001',
                'name' => 'DTF Adhesive Powder',
                'description' => 'Bubuk perekat untuk DTF printing',
                'category_code' => 'DTF',
                'price' => 180000,
                'cost_price' => 120000,
                'stock' => 15,
                'min_stock' => 5,
                'unit' => 'kg',
            ],
            
            [
                'code' => 'EMB-THR-001',
                'name' => 'Benang Bordir Polyester',
                'description' => 'Benang bordir polyester berkualitas tinggi',
                'category_code' => 'EMB',
                'price' => 25000,
                'cost_price' => 15000,
                'stock' => 150,
                'min_stock' => 30,
                'unit' => 'gulung',
            ],
            [
                'code' => 'EMB-CAP-001',
                'name' => 'Bordir Topi Baseball',
                'description' => 'Jasa bordir untuk topi baseball',
                'category_code' => 'EMB',
                'price' => 45000,
                'cost_price' => 25000,
                'stock' => 60,
                'min_stock' => 20,
                'unit' => 'pcs',
            ],
            
            [
                'code' => 'ACC-VINYL-001',
                'name' => 'Vinyl Cutting Putih',
                'description' => 'Vinyl cutting warna putih untuk heat transfer',
                'category_code' => 'ACC',
                'price' => 35000,
                'cost_price' => 20000,
                'stock' => 40,
                'min_stock' => 10,
                'unit' => 'meter',
            ],
            [
                'code' => 'ACC-HEAT-001',
                'name' => 'Heat Transfer Paper',
                'description' => 'Kertas transfer panas untuk sablon digital',
                'category_code' => 'ACC',
                'price' => 8000,
                'cost_price' => 5000,
                'stock' => 300,
                'min_stock' => 100,
                'unit' => 'lembar',
            ],
            
            [
                'code' => 'PKG-BAG-001',
                'name' => 'Plastik Packaging OPP',
                'description' => 'Plastik kemasan OPP untuk produk',
                'category_code' => 'PKG',
                'price' => 2500,
                'cost_price' => 1500,
                'stock' => 500,
                'min_stock' => 200,
                'unit' => 'pcs',
            ],
        ];

        foreach ($products as $productData) {
            $categoryCode = $productData['category_code'];
            unset($productData['category_code']);
            
            $category = $categories[$categoryCode] ?? null;
            if (!$category) continue;

            Product::create([
                ...$productData,
                'category_id' => $category->code,
                'is_active' => true,
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]);
        }
    }
}