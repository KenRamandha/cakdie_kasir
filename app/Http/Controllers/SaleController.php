<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\PrintLog;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\User;
use App\Models\CompanySetting;
use App\Http\Controllers\Traits\ChecksPermissions;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Support\Facades\Storage;

class SaleController extends Controller
{
    use ChecksPermissions;

    public function index(Request $request)
    {
        try {
            $query = Sale::with(['cashier', 'saleItems.product', 'customer']);

            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                $query->whereBetween('transaction_date', [$startDate, $endDate]);
            }

            if ($request->has('cashier_user_id')) {
                $cashier = User::where('user_id', $request->cashier_user_id)->first();
                if ($cashier) {
                    $query->where('cashier_id', $cashier->user_id);
                }
            }

            $sales = $query->orderBy('transaction_date', 'desc')->paginate(20);

            return response()->json($sales);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data penjualan: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'items' => 'required|array|min:1',
                'items.*.product_code' => 'required|exists:products,code',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.discount' => 'nullable|numeric|min:0',
                'payment_method' => 'required|in:cash,card,transfer',
                'cash_received' => 'nullable|numeric',
                'tax' => 'nullable|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
                'customer_phone' => 'nullable|string',
                'customer_name' => 'required_with:customer_phone|string',
            ]);

            return DB::transaction(function () use ($request) {
                $subtotal = 0;
                $saleItems = [];

                foreach ($request->items as $item) {
                    $product = Product::where('code', $item['product_code'])->firstOrFail();

                    if ($product->stock < $item['quantity']) {
                        return response()->json([
                            'message' => 'Stok tidak mencukupi untuk produk ' . $product->name,
                            'errors' => [
                                'stock' => ['Stok tersedia: ' . $product->stock]
                            ]
                        ], 422);
                    }

                    $itemDiscount = $item['discount'] ?? 0;
                    $totalPrice = ($product->price * $item['quantity']) - $itemDiscount;
                    $subtotal += $totalPrice + $itemDiscount;

                    $saleItems[] = [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total_price' => $totalPrice,
                        'discount' => $itemDiscount,
                    ];
                }

                $tax = $request->tax ?? 0;
                $discount = $request->discount ?? 0;
                $total = $subtotal + $tax - $discount;

                if ($request->payment_method === 'cash') {
                    if (!$request->cash_received || $request->cash_received < $total) {
                        return response()->json([
                            'message' => 'Jumlah uang yang diterima tidak mencukupi',
                            'errors' => [
                                'payment' => ['Total yang harus dibayar: ' . $total]
                            ]
                        ], 422);
                    }
                }

                $customer = null;
                if ($request->customer_phone) {
                    $customer = Customer::firstOrCreate(
                        ['phone' => $request->customer_phone],
                        ['name' => $request->customer_name]
                    );

                    $customer->increment('purchase_count');
                    $customer->update(['last_purchase_at' => now()]);
                }

                $sale = Sale::create([
                    'code' => 'TRX-' . date('Ymd') . '-' . Str::random(6),
                    'subtotal' => $subtotal - array_sum(array_column($saleItems, 'discount')),
                    'tax' => $tax,
                    'discount' => $discount,
                    'total' => $total,
                    'cash_received' => $request->cash_received,
                    'change_amount' => $request->payment_method === 'cash' ?
                        ($request->cash_received - $total) : 0,
                    'payment_method' => $request->payment_method,
                    'notes' => $request->notes,
                    'cashier_id' => $request->user()->user_id,
                    'customer_id' => $customer ? $customer->customer_id : null,
                    'transaction_date' => now(),
                ]);

                foreach ($saleItems as $item) {
                    SaleItem::create([
                        'code' => 'ITM-' . Str::random(8),
                        'sale_id' => $sale->code,
                        'product_id' => $item['product']->code,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'discount' => $item['discount'],
                    ]);

                    $product = $item['product'];
                    $stockBefore = $product->stock;
                    $stockAfter = $stockBefore - $item['quantity'];

                    $product->update(['stock' => $stockAfter]);

                    StockLog::create([
                        'code' => 'STK-' . Str::random(8),
                        'product_id' => $product->code,
                        'type' => 'out',
                        'quantity' => $item['quantity'],
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'notes' => 'Sale transaction: ' . $sale->code,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->code,
                        'created_by' => $request->user()->user_id,
                    ]);
                }

                return response()->json($sale->load(['saleItems.product', 'cashier']), 201);
            });
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat transaksi: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $sale = Sale::with(['cashier', 'saleItems.product', 'customer'])
                ->where('code', $code)
                ->firstOrFail();
            return response()->json($sale);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan',
                'errors' => [
                    'not_found' => ['Transaksi dengan kode tersebut tidak ditemukan']
                ]
            ], 404);
        }
    }

    public function getProductsByCategory(Request $request)
    {
        try {
            $categoryCode = $request->get('category_code');

            $query = Product::with('category')->where('is_active', true)->where('stock', '>', 0);

            if ($categoryCode) {
                $category = Category::where('code', $categoryCode)->first();
                if ($category) {
                    $query->where('category_id', $category->code);
                }
            }

            $products = $query->get()->map(function ($product) {
                $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;
                return $product;
            });

            return response()->json($products);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data produk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function printReceipt(Request $request, $code)
    {
        try {
            $sale = Sale::with(['cashier', 'saleItems.product'])
                ->where('code', $code)
                ->firstOrFail();

            $isReprint = PrintLog::where('sale_id', $sale->code)->exists();

            PrintLog::create([
                'code' => 'PRT-' . Str::random(8),
                'sale_id' => $sale->code,
                'printed_by' => $request->user()->user_id,
                'printed_at' => now(),
                'printer_name' => $request->printer_name ?? 'Default Printer',
                'print_type' => 'receipt',
                'is_reprint' => $isReprint,
            ]);

            return response()->json([
                'sale' => $sale,
                'receipt_data' => $this->generateReceiptData($sale),
                'is_reprint' => $isReprint
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mencetak struk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function deleteSale(Request $request, $code)
    {
        try {
            $this->checkOwnerPermission($request->user());

            return DB::transaction(function () use ($code, $request) {
                $sale = Sale::with('saleItems')->where('code', $code)->firstOrFail();

                foreach ($sale->saleItems as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $stockBefore = $product->stock;
                        $stockAfter = $stockBefore + $item->quantity;

                        $product->update(['stock' => $stockAfter]);

                        StockLog::create([
                            'code' => 'STK-' . Str::random(8),
                            'product_id' => $product->code,
                            'type' => 'in',
                            'quantity' => $item->quantity,
                            'stock_before' => $stockBefore,
                            'stock_after' => $stockAfter,
                            'notes' => 'Sale cancellation: ' . $sale->code,
                            'reference_type' => 'sale_cancellation',
                            'reference_id' => $sale->code,
                            'created_by' => $request->user()->user_id,
                        ]);
                    }
                }

                $sale->saleItems()->delete();
                $sale->printLogs()->delete();
                $sale->delete();

                return response()->json(['message' => 'Transaksi berhasil dihapus']);
            });
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => 'Akses ditolak: Anda tidak memiliki izin untuk menghapus transaksi',
                'errors' => [
                    'permission' => ['Anda tidak memiliki izin yang diperlukan']
                ]
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menghapus transaksi: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    private function generateReceiptData($sale)
    {
        $companySettings = CompanySetting::first();

        return [
            'store_info' => [
                'name' => $companySettings->name ?? 'Toko Saya',
                'address' => $companySettings->address ?? 'Jl. Contoh No. 123, Jakarta',
                'phone' => $companySettings->phone ?? '08123456789',
                'logo_url' => $companySettings->logo_path ? Storage::url($companySettings->logo_path) : null,
            ],
            'transaction' => [
                'code' => $sale->code,
                'date' => $sale->transaction_date->format('d/m/Y H:i:s'),
                'cashier' => $sale->cashier->name,
                'customer' => $sale->customer ? $sale->customer->name : null,
                'customer_phone' => $sale->customer ? $sale->customer->phone : null,
            ],
            'items' => $sale->saleItems->map(function ($item) {
                return [
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'total' => $item->total_price,
                ];
            }),
            'summary' => [
                'subtotal' => $sale->subtotal,
                'tax' => $sale->tax,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'cash_received' => $sale->cash_received,
                'change' => $sale->change_amount,
                'payment_method' => $sale->payment_method,
            ],
            'notes' => $sale->notes,
            'footer' => $companySettings->receipt_footer ?? 'Terima kasih telah berbelanja',
        ];
    }
}
