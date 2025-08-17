<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('sales as purchase_count');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%");
            });
        }

        $customers = $query->orderBy('purchase_count', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($customers);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer = Customer::create([
                'customer_id' => Str::uuid(),
                'name' => $request->name,
                'phone' => $request->phone,
            ]);

            return response()->json([
                'message' => 'Customer created successfully',
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($customer_id)
    {
        $customer = Customer::where('customer_id', $customer_id)->firstOrFail();
        return response()->json($customer);
    }

    public function update(Request $request, $customer_id)
    {
        $customer = Customer::where('customer_id', $customer_id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20|unique:customers,phone,' . $customer->customer_id . ',customer_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer->update($request->only(['name', 'phone']));

            return response()->json([
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPurchaseStats(Request $request, $customer_id)
    {
        $customer = Customer::where('customer_id', $customer_id)->firstOrFail();

        $stats = DB::table('sales')
            ->select(
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('COUNT(DISTINCT MONTH(transaction_date)) as active_months')
            )
            ->where('customer_id', $customer_id)
            ->first();

        // Handle cases where there are no sales
        $stats = (object) [
            'total_transactions' => $stats->total_transactions ?? 0,
            'total_spent' => $stats->total_spent ?? 0,
            'active_months' => $stats->active_months ?? 0
        ];

        $monthlyStats = DB::table('sales')
            ->select(
                DB::raw('YEAR(transaction_date) as year'),
                DB::raw('MONTH(transaction_date) as month'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total) as total_spent')
            )
            ->where('customer_id', $customer_id)
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json([
            'customer' => $customer,
            'overall_stats' => $stats,
            'monthly_stats' => $monthlyStats
        ]);
    }

    public function getPurchaseHistory(Request $request, $customer_id)
    {
        $customer = Customer::where('customer_id', $customer_id)->firstOrFail();

        $query = Sale::with(['saleItems.product', 'cashier'])
            ->where('customer_id', $customer_id)
            ->orderBy('transaction_date', 'desc');

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }

        $purchases = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'customer' => $customer,
            'purchases' => $purchases
        ]);
    }
}
