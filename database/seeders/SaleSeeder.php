<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sale;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $cashier = User::where('user_id', 'USR-0001')->first(); // pastikan ada user_id = USR-0001

        if (!$cashier) {
            $this->command->error("Cashier with user_id 'USR-0001' not found. Please seed users first.");
            return;
        }

        $sale = Sale::create([
            'code' => 'SALE-' . strtoupper(Str::random(6)),
            'subtotal' => 170000,
            'tax' => 10000,
            'discount' => 5000,
            'total' => 175000,
            'cash_received' => 200000,
            'change_amount' => 25000,
            'payment_method' => 'cash',
            'notes' => 'Transaksi percobaan',
            'cashier_id' => $cashier->user_id,
            'transaction_date' => Carbon::now(),
        ]);

        // Simpan ID untuk dipakai SaleItemSeeder
        cache()->put('latest_sale_id', $sale->id);
    }
}
