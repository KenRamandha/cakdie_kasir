<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\PrintLogController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CompanySettingController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| This file contains all API routes for the POS (Point of Sale) system.
| Routes are organized by functionality and use custom identifiers 
| (codes/user_ids) instead of auto-increment IDs for better security.
|
*/

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Authentication Required)
|--------------------------------------------------------------------------
*/

/**
 * Authentication Login
 * POST /api/login
 * 
 * Purpose: Authenticate user and get access token
 * Body: { username: string, password: string }
 * Response: { access_token, token_type, user }
 */
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (Authentication Required)
|--------------------------------------------------------------------------
| All routes below require valid Bearer token in Authorization header
*/
Route::middleware('auth:sanctum')->group(function () {

     /*
    |----------------------------------------------------------------------
    | AUTHENTICATION ROUTES
    |----------------------------------------------------------------------
    */

     /**
      * Logout User
      * POST /api/logout
      * 
      * Purpose: Revoke current access token and logout user
      * Headers: Authorization: Bearer {token}
      * Response: { message: "Logged out successfully" }
      */
     Route::post('/logout', [AuthController::class, 'logout']);

     /**
      * Get Current User Info
      * GET /api/user
      * 
      * Purpose: Get authenticated user's information
      * Headers: Authorization: Bearer {token}
      * Response: User object with all details
      */
     Route::get('/user', function (Request $request) {
          return $request->user();
     });

     /*
    |----------------------------------------------------------------------
    | DASHBOARD ROUTES
    |----------------------------------------------------------------------
    */

     /**
      * Dashboard Data
      * GET /api/dashboard
      * 
      * Purpose: Get dashboard statistics and charts data
      * Query Params: ?period=daily|weekly|monthly
      * Response: Revenue charts, stock charts, sales history, low stock alerts
      * Access: All authenticated users (different data for pemilik vs pegawai)
      */
     Route::get('/dashboard', [DashboardController::class, 'index']);


     Route::get('/dashboard/export-sales/check', [DashboardController::class, 'checkExportSize']);

     /**
      * Export Sales Data
      * GET /api/dashboard/export-sales
      * 
      * Purpose: Export sales data to CSV file
      * Query Params: ?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
      * Response: CSV file download
      * Access: Pemilik only
      */
     Route::get('/dashboard/export-sales', [DashboardController::class, 'exportSales']);

     /*
    |----------------------------------------------------------------------
    | CATEGORY MANAGEMENT ROUTES
    |----------------------------------------------------------------------
    */

     Route::prefix('categories')->group(function () {
          /**
           * List All Categories
           * GET /api/categories
           * 
           * Purpose: Get all active categories
           * Response: Array of category objects
           */
          Route::get('/', [CategoryController::class, 'index']);

          /**
           * Create New Category
           * POST /api/categories
           * 
           * Purpose: Create a new product category
           * Body: { code: string, name: string, description?: string }
           * Response: Created category object
           * Access: All authenticated users
           */
          Route::post('/', [CategoryController::class, 'store']);

          /**
           * Get Specific Category
           * GET /api/categories/{code}
           * 
           * Purpose: Get category details by code
           * Params: code - Category code (e.g., CAT-001)
           * Response: Category object
           */
          Route::get('/{code}', [CategoryController::class, 'show'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Update Category
           * PUT /api/categories/{code}
           * 
           * Purpose: Update existing category
           * Params: code - Category code
           * Body: { code: string, name: string, description?: string }
           * Response: Updated category object
           */
          Route::put('/{code}', [CategoryController::class, 'update'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Update Category (Partial)
           * PATCH /api/categories/{code}
           * 
           * Purpose: Partially update category (same as PUT)
           * Params: code - Category code
           * Body: Fields to update
           * Response: Updated category object
           */
          Route::patch('/{code}', [CategoryController::class, 'update'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Delete Category (Soft Delete)
           * DELETE /api/categories/{code}
           * 
           * Purpose: Deactivate category (set is_active = false)
           * Params: code - Category code
           * Response: { message: "Category deactivated successfully" }
           */
          Route::delete('/{code}', [CategoryController::class, 'destroy'])
               ->where('code', '[A-Za-z0-9\-_]+');
     });

     /*
    |----------------------------------------------------------------------
    | PRODUCT MANAGEMENT ROUTES
    |----------------------------------------------------------------------
    */

     Route::prefix('products')->group(function () {
          /**
           * List All Products
           * GET /api/products
           * 
           * Purpose: Get all active products with category info
           * Query Params: ?category_code={code} - Filter by category
           * Response: Array of product objects with category relations
           */
          Route::get('/', [ProductController::class, 'index']);

          /**
           * Create New Product
           * POST /api/products
           * 
           * Purpose: Create a new product with initial stock
           * Body: {
           *   code: string,
           *   name: string,
           *   category_code: string,
           *   price: number,
           *   cost_price?: number,
           *   stock: number,
           *   min_stock?: number,
           *   unit?: string,
           *   description?: string
           * }
           * Response: Created product object with category
           * Note: Automatically creates stock log for initial stock
           */
          Route::post('/', [ProductController::class, 'store']);

          /**
           * Get Specific Product
           * GET /api/products/{code}
           * 
           * Purpose: Get product details by code
           * Params: code - Product code (e.g., PRD-001)
           * Response: Product object with category relation
           */
          Route::get('/{code}', [ProductController::class, 'show'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Update Product
           * PUT /api/products/{code}
           * 
           * Purpose: Update existing product (except stock)
           * Params: code - Product code
           * Body: Product fields to update (stock not included)
           * Response: Updated product object
           * Note: Use update-stock endpoint for stock changes
           */
          Route::put('/{code}', [ProductController::class, 'update'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Update Product (Partial)
           * PATCH /api/products/{code}
           * 
           * Purpose: Partially update product
           * Params: code - Product code
           * Body: Fields to update
           * Response: Updated product object
           */
          Route::patch('/{code}', [ProductController::class, 'update'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Update Product Stock
           * POST /api/products/{code}/update-stock
           * 
           * Purpose: Update product stock quantity
           * Params: code - Product code
           * Body: {
           *   quantity: number,
           *   type: "in"|"out"|"adjustment",
           *   notes?: string
           * }
           * Response: Updated product object
           * Note: Automatically creates stock log entry
           */
          Route::post('/{code}/update-stock', [ProductController::class, 'updateStock'])
               ->where('code', '[A-Za-z0-9\-_]+');

          Route::delete('/{code}', [ProductController::class, 'destroy'])
               ->where('code', '[A-Za-z0-9\-_]+');
     });

     /*
    |----------------------------------------------------------------------
    | SALES MANAGEMENT ROUTES
    |----------------------------------------------------------------------
    */

     Route::prefix('sales')->group(function () {
          /**
           * List All Sales
           * GET /api/sales
           * 
           * Purpose: Get sales transactions with pagination
           * Query Params: 
           *   ?start_date=YYYY-MM-DD - Filter from date
           *   ?end_date=YYYY-MM-DD - Filter to date
           *   ?cashier_user_id={user_id} - Filter by cashier
           *   ?page={number} - Pagination
           * Response: Paginated sales with cashier and items relations
           */
          Route::get('/', [SaleController::class, 'index']);

          /**
           * Create New Sale Transaction
           * POST /api/sales
           * 
           * Purpose: Process a new sale transaction
           * Body: {
           *   items: [{
           *     product_code: string,
           *     quantity: number,
           *     discount?: number
           *   }],
           *   payment_method: "cash"|"card"|"transfer",
           *   cash_received?: number,
           *   tax?: number,
           *   discount?: number,
           *   notes?: string
           * }
           * Response: Created sale with items and cashier info
           * Note: Automatically updates product stock and creates stock logs
           */
          Route::post('/', [SaleController::class, 'store']);

          /**
           * Get Specific Sale
           * GET /api/sales/{code}
           * 
           * Purpose: Get sale transaction details
           * Params: code - Sale code (e.g., TRX-20250127-ABC123)
           * Response: Sale object with cashier and items relations
           */
          Route::get('/{code}', [SaleController::class, 'show'])
               ->where('code', '[A-Za-z0-9\-_]+');

          /**
           * Print Receipt
           * POST /api/sales/{code}/print-receipt
           * 
           * Purpose: Generate receipt data and log print action
           * Params: code - Sale code
           * Body: { printer_name?: string }
           * Response: {
           *   sale: object,
           *   receipt_data: formatted_data,
           *   is_reprint: boolean
           * }
           * Note: Creates print log entry
           */
          Route::post('/{code}/print-receipt', [SaleController::class, 'printReceipt'])
               ->where('code', '[A-Za-z0-9\-_]+');
     });

     /**
      * Get Products by Category (for POS interface)
      * GET /api/products-by-category
      * 
      * Purpose: Get available products filtered by category for sale
      * Query Params: ?category_code={code} - Category filter
      * Response: Array of products with stock > 0
      * Usage: Typically used in POS interface for product selection
      */
     Route::get('/products-by-category', [SaleController::class, 'getProductsByCategory']);

     /*
    |----------------------------------------------------------------------
    | OWNER-ONLY SALES ROUTES
    |----------------------------------------------------------------------
    */
     Route::prefix('sales')->group(function () {
          /**
           * Delete Sale Transaction
           * DELETE /api/sales/{code}
           * 
           * Purpose: Cancel/delete a sale transaction
           * Params: code - Sale code
           * Response: { message: "Sale deleted successfully" }
           * Access: Pemilik only
           * Note: Restores product stock and creates reversal stock logs
           */
          Route::delete('/{code}', [SaleController::class, 'deleteSale'])
               ->where('code', '[A-Za-z0-9\-_]+');
     });

     /*
    |----------------------------------------------------------------------
    | STOCK MANAGEMENT ROUTES
    |----------------------------------------------------------------------
    */

     Route::prefix('stock')->group(function () {
          /**
           * Stock Transaction Logs
           * GET /api/stock/logs
           * 
           * Purpose: Get stock movement history with pagination
           * Query Params:
           *   ?product_code={code} - Filter by product
           *   ?type=in|out|adjustment - Filter by transaction type
           *   ?page={number} - Pagination
           * Response: Paginated stock logs with product and user relations
           */
          Route::get('/logs', [StockController::class, 'logs']);

          /**
           * Low Stock Products
           * GET /api/stock/low-stock
           * 
           * Purpose: Get products with stock <= minimum stock level
           * Response: Array of products with category info
           * Usage: Inventory alerts and reorder notifications
           */
          Route::get('/low-stock', [StockController::class, 'lowStock']);
     });

     /*
    |----------------------------------------------------------------------
    | PRINT LOG ROUTES
    |----------------------------------------------------------------------
    */

     Route::prefix('print-logs')->group(function () {
          /**
           * Print History
           * GET /api/print-logs
           * 
           * Purpose: Get receipt printing history
           * Response: Array of print logs with user relations
           * Usage: Audit trail for receipt printing
           */
          Route::get('/', [PrintLogController::class, 'index']);

          /**
           * Log Print Action
           * POST /api/print-logs
           * 
           * Purpose: Manually log a print action
           * Body: { sale_code: string }
           * Response: Created print log object
           * Note: Usually called automatically by print-receipt endpoint
           */
          Route::post('/', [PrintLogController::class, 'store']);
     });

     /*
    |----------------------------------------------------------------------
    | REPORT ROUTES (OWNER ONLY)
    |----------------------------------------------------------------------
    */

     Route::prefix('reports')->group(function () {
          /**
           * Sales Report
           * GET /api/reports/sales
           * 
           * Purpose: Generate comprehensive sales report
           * Query Params:
           *   ?start_date=YYYY-MM-DD - Report start date
           *   ?end_date=YYYY-MM-DD - Report end date
           * Response: {
           *   summary: {
           *     total_transactions,
           *     total_revenue,
           *     total_discount,
           *     total_tax,
           *     average_transaction,
           *     payment_methods
           *   },
           *   transactions: detailed_sales_data
           * }
           * Access: Pemilik only
           */
          Route::get('/sales', [ReportController::class, 'salesReport']);

          /**
           * Top Products Report
           * GET /api/reports/top-products
           * 
           * Purpose: Get best-selling products report
           * Query Params:
           *   ?start_date=YYYY-MM-DD - Report start date
           *   ?end_date=YYYY-MM-DD - Report end date
           * Response: Array of products with total_sold and total_revenue
           * Access: Pemilik only
           * Limit: Top 10 products
           */
          Route::get('/top-products', [ReportController::class, 'topProducts']);
     });

     /*
    |----------------------------------------------------------------------
    | USER MANAGEMENT ROUTES (OWNER ONLY)
    |----------------------------------------------------------------------
    */

     Route::prefix('users')->group(function () {
          /**
           * List All Users
           * GET /api/users
           * 
           * Purpose: Get all system users
           * Response: Array of users (password hidden)
           * Access: Pemilik only
           */
          Route::get('/', [UserController::class, 'index']);

          /**
           * Create New User
           * POST /api/users
           * 
           * Purpose: Create new system user
           * Body: {
           *   name: string,
           *   username: string,
           *   email: string,
           *   password: string,
           *   role: "pemilik"|"pegawai"
           * }
           * Response: { message, user }
           * Access: Pemilik only
           * Note: Auto-generates user_id with format USR-{random}
           */
          Route::post('/', [UserController::class, 'store']);

          /**
           * Get Specific User
           * GET /api/users/{user_id}
           * 
           * Purpose: Get user details by user_id
           * Params: user_id - User ID (e.g., USR-12345678)
           * Response: User object (password hidden)
           * Access: Pemilik only
           */
          Route::get('/{user_id}', [UserController::class, 'show'])
               ->where('user_id', '[A-Za-z0-9\-_]+');

          /**
           * Update User
           * PUT /api/users/{user_id}
           * 
           * Purpose: Update existing user
           * Params: user_id - User ID
           * Body: {
           *   name: string,
           *   username: string,
           *   email: string,
           *   password?: string,
           *   role: "pemilik"|"pegawai"
           * }
           * Response: { message, user }
           * Access: Pemilik only
           */
          Route::put('/{user_id}', [UserController::class, 'update'])
               ->where('user_id', '[A-Za-z0-9\-_]+');

          /**
           * Update User (Partial)
           * PATCH /api/users/{user_id}
           * 
           * Purpose: Partially update user
           * Params: user_id - User ID
           * Body: Fields to update
           * Response: { message, user }
           * Access: Pemilik only
           */
          Route::patch('/{user_id}', [UserController::class, 'update'])
               ->where('user_id', '[A-Za-z0-9\-_]+');

          /**
           * Delete User (Soft Delete)
           * DELETE /api/users/{user_id}
           * 
           * Purpose: Deactivate user (set is_active = false)
           * Params: user_id - User ID
           * Response: { message: "User deactivated successfully" }
           * Access: Pemilik only
           * Restriction: Cannot delete own account
           */
          Route::delete('/{user_id}', [UserController::class, 'destroy'])
               ->where('user_id', '[A-Za-z0-9\-_]+');

          /**
           * Toggle User Status
           * POST /api/users/{user_id}/toggle-status
           * 
           * Purpose: Activate/deactivate user account
           * Params: user_id - User ID
           * Response: { message, user }
           * Access: Pemilik only
           * Restriction: Cannot deactivate own account
           */
          Route::post('/{user_id}/toggle-status', [UserController::class, 'toggleStatus'])
               ->where('user_id', '[A-Za-z0-9\-_]+');
     });
     // Public route for getting receipt settings
     Route::get('/company-settings/public', [CompanySettingController::class, 'getPublicSettings']);

     // Protected routes (harus ada user yang login)
     Route::prefix('company-settings')->group(function () {
          Route::get('/', [CompanySettingController::class, 'getFullSettings']);
          Route::post('/', [CompanySettingController::class, 'saveSettings']);
     });
});
