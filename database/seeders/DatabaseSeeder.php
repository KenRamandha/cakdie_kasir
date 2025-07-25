<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            SaleSeeder::class,
            SaleItemSeeder::class,
            StockLogsSeeder::class,
            PrintLogsSeeder::class,
        ]);
    }
}

