<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\User;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $user = User::where('user_id', 'USR-0001')->first();

        if (!$user) {
            $this->command->error('User dengan user_id USR-0001 tidak ditemukan.');
            return;
        }

        $categories = [
            [
                'code' => 'SCR',
                'name' => 'Screen Printing',
                'description' => 'Produk dan layanan sablon screen printing',
                'is_active' => true,
            ],
            [
                'code' => 'DTF',
                'name' => 'DTF Printing',
                'description' => 'Direct to Film printing services',
                'is_active' => true,
            ],
            [
                'code' => 'EMB',
                'name' => 'Embroidery',
                'description' => 'Jasa bordir kustom',
                'is_active' => true,
            ],
            [
                'code' => 'ACC',
                'name' => 'Accessories',
                'description' => 'Aksesoris dan bahan pendukung',
                'is_active' => true,
            ],
            [
                'code' => 'PKG',
                'name' => 'Packaging',
                'description' => 'Kemasan dan packaging',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create(array_merge($category, [
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]));
        }
    }
}