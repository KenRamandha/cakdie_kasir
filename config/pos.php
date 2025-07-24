<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    |
    | This information will be used in receipts and reports
    |
    */

    'company_name' => env('COMPANY_NAME', 'POS System'),
    'company_address' => env('COMPANY_ADDRESS', 'Jl. Contoh No. 123, Jakarta'),
    'company_phone' => env('COMPANY_PHONE', '021-12345678'),
    'company_email' => env('COMPANY_EMAIL', 'info@pos.com'),

    /*
    |--------------------------------------------------------------------------
    | Receipt Settings
    |--------------------------------------------------------------------------
    |
    | Configure receipt printing settings
    |
    */

    'receipt' => [
        'width' => 48, // characters
        'show_logo' => env('RECEIPT_SHOW_LOGO', false),
        'logo_path' => env('RECEIPT_LOGO_PATH', ''),
        'footer_text' => env('RECEIPT_FOOTER_TEXT', 'Terima kasih atas kunjungan Anda'),
        'return_policy' => env('RECEIPT_RETURN_POLICY', 'Barang yang sudah dibeli tidak dapat dikembalikan'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Settings
    |--------------------------------------------------------------------------
    |
    | Configure tax calculations
    |
    */

    'tax' => [
        'enabled' => env('TAX_ENABLED', false),
        'rate' => env('TAX_RATE', 10), // percentage
        'inclusive' => env('TAX_INCLUSIVE', false), // true if tax is included in price
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Settings
    |--------------------------------------------------------------------------
    |
    | Configure currency display
    |
    */

    'currency' => [
        'symbol' => env('CURRENCY_SYMBOL', 'Rp'),
        'position' => env('CURRENCY_POSITION', 'before'), // before or after
        'decimal_places' => env('CURRENCY_DECIMAL_PLACES', 0),
        'thousands_separator' => env('CURRENCY_THOUSANDS_SEPARATOR', '.'),
        'decimal_separator' => env('CURRENCY_DECIMAL_SEPARATOR', ','),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stock Settings
    |--------------------------------------------------------------------------
    |
    | Configure stock management
    |
    */

    'stock' => [
        'allow_negative' => env('STOCK_ALLOW_NEGATIVE', false),
        'low_stock_notification' => env('STOCK_LOW_NOTIFICATION', true),
        'auto_generate_codes' => env('STOCK_AUTO_GENERATE_CODES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sales Settings
    |--------------------------------------------------------------------------
    |
    | Configure sales behavior
    |
    */

    'sales' => [
        'auto_print_receipt' => env('SALES_AUTO_PRINT_RECEIPT', true),
        'require_customer_info' => env('SALES_REQUIRE_CUSTOMER_INFO', false),
        'allow_discount' => env('SALES_ALLOW_DISCOUNT', true),
        'max_discount_percent' => env('SALES_MAX_DISCOUNT_PERCENT', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic backup settings
    |
    */

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', false),
        'frequency' => env('BACKUP_FREQUENCY', 'daily'), // daily, weekly, monthly
        'keep_backups' => env('BACKUP_KEEP_COUNT', 7),
        'path' => env('BACKUP_PATH', storage_path('app/backups')),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Configure API behavior
    |
    */

    'api' => [
        'pagination_per_page' => env('API_PAGINATION_PER_PAGE', 15),
        'max_per_page' => env('API_MAX_PER_PAGE', 100),
        'rate_limit' => env('API_RATE_LIMIT', 60), // requests per minute
    ],

];