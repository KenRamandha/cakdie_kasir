<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\PrintLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with(['cashier', 'saleItems.product'])
                     ->orderBy('transaction_date', 'desc')
                     ->paginate(20);
        
        return response()->json($sales);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:cash,card,transfer',
            'cash_received' => 'nullable|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $subtotal = 0;
            $saleItems = [];

            // Calculate subtotal and prepare sale items
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Check stock availability
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock tidak mencukupi untuk produk {$product->name}");
                }

                $totalPrice = $product->price * $item['quantity'];
                $subtotal += $totalPrice;

                $saleItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $totalPrice,
                    'discount' => $item['discount'] ?? 0,
                ];
            }

            $tax = $request->tax ?? 0;
            $discount = $request->discount ?? 0;
            $total = $subtotal + $tax - $discount;

            // Create sale
            $sale = Sale::create([
                'code' => 'TRX-' . date('Ymd') . '-' . Str::random(6),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'cash_received' => $request->cash_received,
                'change_amount' => $request->payment_method === 'cash' ? 
                    ($request->cash_received - $total) : 0,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'cashier_id' => $request->user()->user_id,
            ]);

            // Create sale items and update stock
            foreach ($saleItems as $item) {
                SaleItem::create([
                    'code' => 'ITM-' . Str::random(8),
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'discount' => $item['discount'],
                ]);

                // Update product stock
                $product = $item['product'];
                $stockBefore = $product->stock;
                $stockAfter = $stockBefore - $item['quantity'];
                
                $product->update(['stock' => $stockAfter]);

                // Create stock log
                StockLog::create([
                    'code' => 'STK-' . Str::random(8),
                    'product_id' => $product->id,
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'notes' => 'Sale transaction',
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'created_by' => $request->user()->id,
                ]);
            }

            return response()->json($sale->load(['saleItems.product', 'cashier']), 201);
        });
    }

    public function show($id)
    {
        $sale = Sale::with(['cashier', 'saleItems.product'])->findOrFail($id);
        return response()->json($sale);
    }

    public function printReceipt(Request $request, $id)
    {
        $sale = Sale::with(['cashier', 'saleItems.product'])->findOrFail($id);
        
        // Create print log
        PrintLog::create([
            'code' => 'PRT-' . Str::random(8),
            'sale_id' => $sale->id,
            'printed_by' => $request->user()->id,
            'printer_name' => $request->printer_name,
            'print_type' => 'receipt',
            'is_reprint' => PrintLog::where('sale_id', $id)->exists(),
        ]);

        // Return receipt data for printing
        return response()->json([
            'sale' => $sale,
            'receipt_data' => $this->generateReceiptData($sale)
        ]);
    }

    private function generateReceiptData($sale)
    {
        return [
            'store_name' => 'Toko Saya',
            'address' => 'Alamat Toko',
            'phone' => '08123456789',
            'transaction_code' => $sale->code,
            'date' => $sale->transaction_date->format('d/m/Y H:i:s'),
            'cashier' => $sale->cashier->name,
            'items' => $sale->saleItems->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'price' => $item->unit_price,
                    'total' => $item->total_price,
                ];
            }),
            'subtotal' => $sale->subtotal,
            'tax' => $sale->tax,
            'discount' => $sale->discount,
            'total' => $sale->total,
            'cash_received' => $sale->cash_received,
            'change' => $sale->change_amount,
            'payment_method' => $sale->payment_method,
        ];
    }
}