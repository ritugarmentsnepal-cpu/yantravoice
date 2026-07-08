<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\TTSController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\UGCController;
use App\Http\Controllers\TextToVideoController;

// ── Public Routes ───────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/',         [AuthController::class, 'showLogin'])->name('home');
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);
});

// ── Authenticated Routes ────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout',  [AuthController::class, 'logout'])->name('logout');
    Route::get('/studio',   [StudioController::class, 'index'])->name('studio');
});

// ── Admin Routes ────────────────────────────────────────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                    [DashboardController::class, 'index'])->name('dashboard');

    // Users
    Route::get('/users',               [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}',        [UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/grant', [UserController::class, 'grantCredits'])->name('users.grant');
    Route::post('/users/{user}/toggle',[UserController::class, 'toggleActive'])->name('users.toggle');
    Route::post('/users/{user}/role',  [UserController::class, 'updateRole'])->name('users.role');

    // Transactions
    Route::get('/transactions',        [TransactionController::class, 'index'])->name('transactions.index');

    // Finance (Income vs Expense)
    Route::get('/finance',             [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/finance/expense',    [FinanceController::class, 'storeExpense'])->name('finance.expense.store');
    Route::delete('/finance/expense/{expense}', [FinanceController::class, 'destroyExpense'])->name('finance.expense.destroy');

    // Settings
    Route::get('/settings',            [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings',           [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/sync-avatars', [SettingsController::class, 'syncAvatars'])->name('settings.sync_avatars');

    // Credit Purchases
    Route::get('/purchases',              [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases/{id}/approve',[PurchaseController::class, 'approve'])->name('purchases.approve');
    Route::post('/purchases/{id}/reject', [PurchaseController::class, 'reject'])->name('purchases.reject');
});

// ── UGC Generator Routes ────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/ugc', [UGCController::class, 'index'])->name('ugc.index');
    Route::post('/ugc/generate', [UGCController::class, 'generate'])->name('ugc.generate');
    Route::get('/ugc/status/{job}', [UGCController::class, 'status'])->name('ugc.status');
    Route::get('/ugc/editor/{job}', [UGCController::class, 'editor'])->name('ugc.editor');
    Route::get('/ugc/view/{job}', [UGCController::class, 'show'])->name('ugc.show');
});

// ── Text-to-Video (Veo 3.1) Routes ─────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/text-to-video', [TextToVideoController::class, 'index'])->name('t2v.index');
    Route::post('/text-to-video/generate', [TextToVideoController::class, 'generate'])->name('t2v.generate');
    Route::get('/text-to-video/status/{job}', [TextToVideoController::class, 'status'])->name('t2v.status');
    Route::get('/text-to-video/view/{job}', [TextToVideoController::class, 'show'])->name('t2v.show');
});

Route::get('/fix-db', function() {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return 'Database migration successful! Output: <pre>' . \Illuminate\Support\Facades\Artisan::output() . '</pre>';
    } catch (\Exception $e) {
        return 'Migration error: ' . $e->getMessage();
    }
});
