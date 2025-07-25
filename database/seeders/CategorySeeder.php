<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\User;

class CategorySeeder extends Seeder
{
    public function run()
    {
        // Ambil satu user, misalnya user pertama
        $user = User::first();

        // Jika tidak ada user, hentikan seeder agar tidak error
        if (!$user) {
            $this->command->error('Tidak ada user ditemukan. Buat user terlebih dahulu sebelum menjalankan seeder.');
            return;
        }

        $categories = [
            ['code' => 'SCR-TSHIRT', 'name' => 'Sablon Kaos', 'description' => 'Jasa sablon kaos custom.'],
            ['code' => 'SCR-TOTE', 'name' => 'Sablon Tote Bag', 'description' => 'Jasa sablon tas kain ramah lingkungan.'],
            ['code' => 'SCR-INK', 'name' => 'Tinta Sablon Tekstil', 'description' => 'Tinta sablon khusus untuk kain.'],
        ];

        foreach ($categories as $category) {
            Category::create(array_merge($category, [
                'created_by' => $user->user_id,
                'updated_by' => $user->user_id,
            ]));
        }
    }
}
