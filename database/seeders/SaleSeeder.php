<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sale;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('user_id', ['USR-0001', 'USR-0002'])->get();

        if ($users->isEmpty()) {
            $this->command->error("Users not found. Please seed users first.");
            return;
        }

        $owner = $users->where('user_id', 'USR-0001')->first();
        $kasir = $users->where('user_id', 'USR-0002')->first();

        $salesData = [
            [
                'code' => 'TRX-' . date('Ymd') . '-001',
                'subtotal' => 170000,
                'tax' => 10000,
                'discount' => 5000,
                'total' => 175000,
                'cash_received' => 200000,
                'change_amount' => 25000,
                'payment_method' => 'cash',
                'notes' => 'Pesanan sablon kaos untuk event sekolah',
                'cashier_id' => $owner->user_id, 
                'transaction_date' => Carbon::now()->subDays(2),
            ],
            [
                'code' => 'TRX-' . date('Ymd') . '-002',
                'subtotal' => 95000,
                'tax' => 5000,
                'discount' => 0,
                'total' => 100000,
                'cash_received' => 100000,
                'change_amount' => 0,
                'payment_method' => 'card',
                'notes' => 'Pembelian tinta sablon',
                'cashier_id' => $kasir->user_id, 
                'transaction_date' => Carbon::now()->subDays(1),
            ],
            [
                'code' => 'TRX-' . date('Ymd') . '-003',
                'subtotal' => 240000,
                'tax' => 12000,
                'discount' => 10000,
                'total' => 242000,
                'cash_received' => 250000,
                'change_amount' => 8000,
                'payment_method' => 'cash',
                'notes' => 'Order DTF printing untuk merchandise',
                'cashier_id' => $owner->user_id, 
                'transaction_date' => Carbon::now()->subHours(5),
            ],
            [
                'code' => 'TRX-' . date('Ymd') . '-004',
                'subtotal' => 180000,
                'tax' => 9000,
                'discount' => 15000,
                'total' => 174000,
                'cash_received' => null,
                'change_amount' => 0,
                'payment_method' => 'transfer',
                'notes' => 'Bordir seragam kantor',
                'cashier_id' => $kasir->user_id, 
                'transaction_date' => Carbon::now()->subHours(2),
            ],
            [
                'code' => 'TRX-' . date('Ymd') . '-005',
                'subtotal' => 75000,
                'tax' => 0,
                'discount' => 5000,
                'total' => 70000,
                'cash_received' => 100000,
                'change_amount' => 30000,
                'payment_method' => 'cash',
                'notes' => 'Sablon tote bag promosi',
                'cashier_id' => $owner->user_id,
                'transaction_date' => Carbon::now()->subMinutes(30),
            ],
        ];

        foreach ($salesData as $saleData) {
            Sale::create($saleData);
        }
    }
}