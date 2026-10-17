<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

// There is no public registration route. Accounts are created by an admin;
// the first admin is created with `php artisan smartsave:create-admin`.

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:sensitive')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:sensitive')->name('password.store');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('change-password', [ProfileController::class, 'showForcedChange'])->name('password.change');
    Route::put('change-password', [ProfileController::class, 'forcedChange'])->middleware('throttle:sensitive')->name('password.change.update');

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Goals
    Route::get('goals', [GoalController::class, 'index'])->name('goals.index');
    Route::get('goals/create', [GoalController::class, 'create'])->name('goals.create');
    Route::get('goals/{goal}', [GoalController::class, 'show'])->name('goals.show');
    Route::get('goals/{goal}/edit', [GoalController::class, 'edit'])->name('goals.edit');

    // Savings ledger
    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');

    // Expenses & budget
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::get('budget', [BudgetController::class, 'index'])->name('budget.index');

    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/print', [ReportController::class, 'print'])->name('reports.print');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:sensitive')->name('profile.password');

    // All state-changing financial routes are rate limited.
    Route::middleware('throttle:writes')->group(function () {
        Route::post('goals', [GoalController::class, 'store'])->name('goals.store');
        Route::put('goals/{goal}', [GoalController::class, 'update'])->name('goals.update');
        Route::patch('goals/{goal}/status', [GoalController::class, 'updateStatus'])->name('goals.status');
        Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');

        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::post('transactions/{transaction}/void', [TransactionController::class, 'void'])->name('transactions.void');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

        Route::put('budget', [BudgetController::class, 'update'])->name('budget.update');
    });

    Route::middleware('throttle:exports')->group(function () {
        Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');
        Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // Admin: account management only, no access to anyone's finances.
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->middleware('throttle:sensitive')->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->middleware('throttle:sensitive')->name('users.password');
    });
});
