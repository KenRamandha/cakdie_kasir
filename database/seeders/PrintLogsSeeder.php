<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PrintLog;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Str;

class PrintLogsSeeder extends Seeder
{
    public function run(): void
    {
        $sales = Sale::all();
        $users = User::all();

        if ($sales->isEmpty() || $users->isEmpty()) {
            $this->command->error("Sales or users not found.");
            return;
        }

        foreach ($sales->take(4) as $sale) {
            PrintLog::create([
                'code' => 'PRT-' . strtoupper(Str::random(8)),
                'sale_id' => $sale->id,
                'printed_by' => $users->random()->user_id,
                'printed_at' => $sale->transaction_date->addMinutes(5),
                'printer_name' => collect(['EPSON-58MM', 'CANON-80MM', 'HP-LaserJet'])->random(),
                'print_type' => 'receipt',
                'is_reprint' => false,
            ]);

            if (rand(0, 1)) {
                PrintLog::create([
                    'code' => 'PRT-' . strtoupper(Str::random(8)),
                    'sale_id' => $sale->id,
                    'printed_by' => $users->random()->user_id,
                    'printed_at' => $sale->transaction_date->addMinutes(rand(10, 60)),
                    'printer_name' => collect(['EPSON-58MM', 'CANON-80MM', 'HP-LaserJet'])->random(),
                    'print_type' => collect(['receipt', 'invoice'])->random(),
                    'is_reprint' => true,
                ]);
            }
        }
    }
}