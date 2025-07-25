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
        $sales = Sale::inRandomOrder()->take(10)->get();
        $users = User::inRandomOrder()->take(5)->get();

        foreach ($sales as $sale) {
            PrintLog::create([
                'code' => strtoupper(Str::random(10)),
                'sale_id' => $sale->id,
                'printed_by' => $users->random()->user_id,
                'printed_at' => now(),
                'printer_name' => 'EPSON-58MM',
                'print_type' => collect(['receipt', 'invoice'])->random(),
                'is_reprint' => (bool) rand(0, 1),
            ]);
        }
    }
}
