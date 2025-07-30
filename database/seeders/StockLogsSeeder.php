<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StockLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class StockLogsSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $users = User::all();

        if ($products->isEmpty() || $users->isEmpty()) {
            $this->command->error("Products or users not found.");
            return;
        }

        foreach ($products as $product) {
            StockLog::create([
                'code' => 'STK-' . strtoupper(Str::random(8)),
                'product_id' => $product->code,
                'type' => 'in',
                'quantity' => $product->stock,
                'stock_before' => 0,
                'stock_after' => $product->stock,
                'notes' => 'Initial stock entry',
                'reference_type' => null,
                'reference_id' => null,
                'created_by' => $users->random()->user_id,
                'created_at' => Carbon::now()->subDays(30),
            ]);

            $currentStock = $product->stock;
            for ($i = 0; $i < rand(3, 8); $i++) {
                $type = collect(['in', 'out', 'adjustment'])->random();
                $quantity = rand(1, 20);
                $stockBefore = $currentStock;

                switch ($type) {
                    case 'in':
                        $stockAfter = $stockBefore + $quantity;
                        $notes = 'Stock replenishment';
                        break;
                    case 'out':
                        $quantity = min($quantity, $stockBefore);
                        $stockAfter = $stockBefore - $quantity;
                        $notes = 'Stock usage/sale';
                        break;
                    case 'adjustment':
                        $stockAfter = rand(0, 200);
                        $quantity = abs($stockAfter - $stockBefore);
                        $notes = 'Stock adjustment/inventory count';
                        break;
                }

                StockLog::create([
                    'code' => 'STK-' . strtoupper(Str::random(8)),
                    'product_id' => $product->code,
                    'type' => $type,
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'notes' => $notes,
                    'reference_type' => null,
                    'reference_id' => null,
                    'created_by' => $users->random()->user_id,
                    'created_at' => Carbon::now()->subDays(rand(1, 29)),
                ]);

                $currentStock = $stockAfter;
            }
        }
    }
}
