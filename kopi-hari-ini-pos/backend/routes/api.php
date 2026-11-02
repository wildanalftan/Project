<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OpnameController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RestockController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// ─── Health check (for offline detection ping) ───────────────────────────────
Route::get('/health', fn() => response()->json(['status' => 'ok', 'ts' => now()->toIso8601String()]));

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1'); // 5 attempts per minute

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
    });
});

// ─── Authenticated routes ─────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {

    // Categories (all roles)
    Route::get('/categories', [ProductController::class, 'categories']);

    // Products (POS endpoint: kasir + admin, no HPP)
    Route::get('/products/pos', [ProductController::class, 'posProducts'])
        ->middleware('role:admin,kasir');

    // Products management (admin only for write operations)
    Route::get('/products',            [ProductController::class, 'index']);
    Route::post('/products/import',    [ProductController::class, 'import'])->middleware('role:admin');
    Route::post('/products',           [ProductController::class, 'store'])->middleware('role:admin');
    Route::put('/products/{product}',  [ProductController::class, 'update'])->middleware('role:admin');
    Route::delete('/products/{product}',[ProductController::class, 'destroy'])->middleware('role:admin');
    Route::get('/products/{product}/summary', [ProductController::class, 'summary'])
        ->middleware('role:admin,manajer');

    // Restocks (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/restocks',            [RestockController::class, 'index']);
        Route::post('/restocks/preview',   [RestockController::class, 'preview']);
        Route::post('/restocks',           [RestockController::class, 'store']);
    });

    // Opname (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/opname',              [OpnameController::class, 'index']);
        Route::post('/opname',             [OpnameController::class, 'store']);
        Route::get('/opname/{opname}',     [OpnameController::class, 'show']);
        Route::put('/opname/{opname}/items', [OpnameController::class, 'updateItems']);
        Route::post('/opname/{opname}/lock', [OpnameController::class, 'lock']);
    });

    // Stock movements (admin + manajer)
    Route::middleware('role:admin,manajer')->group(function () {
        Route::get('/stock-movements',        [StockMovementController::class, 'index']);
        Route::get('/stock-movements/export', [StockMovementController::class, 'export']);
    });

    // Transactions
    Route::get('/transactions',            [TransactionController::class, 'index']);
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show']);
    Route::post('/transactions',           [TransactionController::class, 'store'])
        ->middleware('role:admin,kasir');
    Route::post('/transactions/sync',      [TransactionController::class, 'sync'])
        ->middleware('role:admin,kasir');

    // HPP (admin + manajer)
    Route::get('/hpp', [DashboardController::class, 'hpp'])
        ->middleware('role:admin,manajer');

    // Dashboard (admin + manajer)
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:admin,manajer');

    // Reports (admin + manajer)
    Route::middleware('role:admin,manajer')->group(function () {
        Route::get('/reports/profit-loss',        [DashboardController::class, 'profitLoss']);
        Route::get('/reports/profit-loss/export', [DashboardController::class, 'exportProfitLoss']);
        Route::post('/reports/expenses',          [DashboardController::class, 'storeExpense']);
    });

    // Settings
    Route::get('/settings',  [SettingController::class, 'index']);
    Route::put('/settings',  [SettingController::class, 'update'])->middleware('role:admin');

    // Users (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users',          [UserController::class, 'index']);
        Route::post('/users',         [UserController::class, 'store']);
        Route::put('/users/{user}',   [UserController::class, 'update']);
    });
});
