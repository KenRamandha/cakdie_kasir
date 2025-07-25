<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

class StockLogsSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::inRandomOrder()->take(10)->get();
        $users = User::inRandomOrder()->take(5)->get();

        foreach ($products as $product) {
            $stockBefore = rand(10, 100);
            $quantity = rand(1, 20);
            $type = collect(['in', 'out', 'adjustment'])->random();

            $stockAfter = match($type) {
                'in' => $stockBefore + $quantity,
                'out' => $stockBefore - $quantity,
                'adjustment' => rand(0, 200),
            };

            StockLog::create([
                'code' => strtoupper(Str::random(10)),
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'notes' => 'Auto generated for testing',
                'reference_type' => null,
                'reference_id' => null,
                'created_by' => $users->random()->user_id,
            ]);
        }
    }
}
