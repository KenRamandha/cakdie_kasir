<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Support\Str;

class SaleItemSeeder extends Seeder
{
    public function run(): void
    {
        $sale = Sale::first(); // atau where('code', 'SALE-XXXX') tergantung format code Anda
        $saleCode = $sale?->code;

        $products = Product::take(2)->get();

        if ($products->isEmpty()) {
            $this->command->error("No products found. Please seed products first.");
            return;
        }

        foreach ($products as $product) {
            $quantity = rand(1, 3);
            $unitPrice = $product->price;
            $discount = 5000;
            $totalPrice = ($unitPrice * $quantity) - $discount;

            SaleItem::create([
                'code' => 'ITEM-' . strtoupper(Str::random(6)),
                'sale_id' => $saleCode,
                'product_id' => $product->code, // <- ini penting
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'total_price' => $totalPrice,
            ]);
        }
    }
}
