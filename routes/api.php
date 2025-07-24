<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleItemController;
use App\Http\Controllers\StockLogController;
use App\Http\Controllers\PrintLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserManagementController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stock-movement-by-category', [DashboardController::class, 'getStockMovementByCategory']);
    
    // Categories routes
    Route::apiResource('categories', CategoryController::class);
    Route::post('/categories/{category}/toggle', [CategoryController::class, 'toggle']);
    Route::get('/categories-active', [CategoryController::class, 'getActiveCategories']);
    
    // Products routes
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/toggle', [ProductController::class, 'toggle']);
    Route::post('/products/{product}/update-stock', [ProductController::class, 'updateStock']);
    Route::get('/products-active', [ProductController::class, 'getActiveProducts']);
    Route::get('/products-low-stock', [ProductController::class, 'getLowStockProducts']);
    
    // Sales routes
    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
    Route::post('/sales/{sale}/print-receipt', [SaleController::class, 'printReceipt']);
    
    // Sale items routes
    Route::apiResource('sale-items', SaleItemController::class)->only(['index', 'store', 'destroy']);
    
    // Stock logs routes
    Route::get('/stock-logs', [StockLogController::class, 'index']);
    Route::post('/stock-logs', [StockLogController::class, 'store']);
    
    // Print logs routes
    Route::get('/print-logs', [PrintLogController::class, 'index']);
    
    // Routes only for Pemilik (Owner)
    Route::middleware('role:pemilik')->group(function () {
        
        // User registration and management
        Route::post('/register', [AuthController::class, 'register']);
        Route::apiResource('users', UserManagementController::class);
        Route::post('/users/{user}/toggle', [UserManagementController::class, 'toggle']);
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);
        Route::get('/users-stats', [UserManagementController::class, 'getStats']);
        
        // Revenue and reports
        Route::get('/dashboard/revenue-by-period', [DashboardController::class, 'getRevenueByPeriod']);
        Route::get('/sales/report', [SaleController::class, 'getSalesReport']);
        Route::get('/sales/revenue-chart', [SaleController::class, 'getRevenueChart']);
        Route::get('/sales/export', [SaleController::class, 'exportSales']);
        
        // Export other data
        Route::get('/products/export', [ProductController::class, 'exportProducts']);
        Route::get('/stock-logs/export', [StockLogController::class, 'exportStockLogs']);
        
    });
    
});

// Fallback route for API
Route::fallback(function(){
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});