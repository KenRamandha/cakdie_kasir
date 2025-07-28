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
        $sales = Sale::all();
        $products = Product::all();

        if ($sales->isEmpty() || $products->isEmpty()) {
            $this->command->error("Sales or products not found. Please seed them first.");
            return;
        }

        $saleItemsData = [
            [
                'sale_code_suffix' => '001',
                'items' => [
                    ['product_code' => 'SCR-TSHIRT-001', 'quantity' => 2, 'discount' => 5000],
                    ['product_code' => 'SCR-TOTE-001', 'quantity' => 1, 'discount' => 0],
                ]
            ],
            [
                'sale_code_suffix' => '002',
                'items' => [
                    ['product_code' => 'SCR-INK-001', 'quantity' => 1, 'discount' => 0],
                ]
            ],
            [
                'sale_code_suffix' => '003',
                'items' => [
                    ['product_code' => 'DTF-FILM-001', 'quantity' => 10, 'discount' => 5000],
                    ['product_code' => 'DTF-POWDER-001', 'quantity' => 1, 'discount' => 5000],
                ]
            ],
            [
                'sale_code_suffix' => '004',
                'items' => [
                    ['product_code' => 'EMB-CAP-001', 'quantity' => 3, 'discount' => 10000],
                    ['product_code' => 'EMB-THR-001', 'quantity' => 2, 'discount' => 5000],
                ]
            ],
            [
                'sale_code_suffix' => '005',
                'items' => [
                    ['product_code' => 'SCR-TOTE-001', 'quantity' => 1, 'discount' => 5000],
                ]
            ],
        ];

        foreach ($saleItemsData as $saleData) {
            $saleCode = 'TRX-' . date('Ymd') . '-' . $saleData['sale_code_suffix'];
            $sale = Sale::where('code', $saleCode)->first();
            
            if (!$sale) continue;

            foreach ($saleData['items'] as $itemData) {
                $product = Product::where('code', $itemData['product_code'])->first();
                if (!$product) continue;

                $quantity = $itemData['quantity'];
                $unitPrice = $product->price;
                $discount = $itemData['discount'];
                $totalPrice = ($unitPrice * $quantity) - $discount;

                SaleItem::create([
                    'code' => 'ITM-' . strtoupper(Str::random(8)),
                    'sale_id' => $sale->code, 
                    'product_id' => $product->code, 
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total_price' => $totalPrice,
                ]);
            }
        }
    }
}