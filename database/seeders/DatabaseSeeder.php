<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UserSeeder::class,
            // CategorySeeder::class,
            // ProductSeeder::class,
            // SaleSeeder::class,
            // SaleItemSeeder::class,
            // StockLogsSeeder::class,
            // PrintLogsSeeder::class,
            // CompanySettingSeeder::class,
        ]);
        
        $this->command->info('All seeders completed successfully!');
        $this->command->info('Test credentials:');
        $this->command->info('Owner - Username: owner, Password: password123');
        $this->command->info('Kasir - Username: kasir, Password: password123');
    }
}