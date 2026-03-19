<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashClosingController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);
    Route::apiResource('products', ProductController::class);

    // Stock In
    Route::apiResource('stock-ins', StockInController::class)->only(['index', 'store', 'show']);

    // Sales
    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
    Route::post('/sales/{sale}/void', [SaleController::class, 'void']);

    // Stock Adjustments
    Route::apiResource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'store']);
    Route::get('/stock-mutations/{productId}', [StockAdjustmentController::class, 'mutations']);

    // Suppliers (semua bisa lihat, admin bisa kelola)
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/suppliers',           [SupplierController::class, 'store']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
    });

    // Cash Closing
    Route::get('/cash-closings',             [CashClosingController::class, 'index']);
    Route::get('/cash-closings/preview',     [CashClosingController::class, 'preview']);
    Route::post('/cash-closings',            [CashClosingController::class, 'store']);
    Route::get('/cash-closings/{cashClosing}', [CashClosingController::class, 'show']);

    // Reports (admin only)
    Route::middleware('role:admin')->prefix('reports')->group(function () {
        Route::get('/sales-summary',   [ReportController::class, 'salesSummary']);
        Route::get('/top-products',    [ReportController::class, 'topProducts']);
        Route::get('/stock',           [ReportController::class, 'stockReport']);
        Route::get('/profit',          [ReportController::class, 'profitReport']);
        Route::get('/cashier-summary', [ReportController::class, 'cashierSummary']);
        Route::get('/supplier',        [ReportController::class, 'supplierReport']);
    });

    // User management (admin only)
    Route::middleware('role:admin')->apiResource('users', UserController::class);
});
