<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Customer routes
    Route::get('/customers', [CustomerController::class, 'index'])
        ->name('customers.index');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->name('customers.store');

    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->name('customers.create');

    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->name('customers.edit');

    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');

    Route::patch('/customers/{customer}/reactivate', [
        CustomerController::class,
        'reactivate',
    ])->name('customers.reactivate');

    Route::patch('/customers/{customer}/deactivate', [
        CustomerController::class,
        'deactivate',
    ])->name('customers.deactivate');

    // Invoice routes
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->name('invoices.index');

    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->name('invoices.store');

    Route::get('/invoices/create', [InvoiceController::class, 'create'])
        ->name('invoices.create');

    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
        ->name('invoices.show');

    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])
        ->name('invoices.payments.store');

    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])
        ->name('invoices.edit');

    Route::patch('/invoices/{invoice}', [InvoiceController::class, 'update'])
        ->name('invoices.update');

    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])
        ->name('invoices.destroy');

    // Dashboard route
    Route::get('/dashboard', [DashboardController::class, '__invoke'])
        ->name('dashboard');

    // Report routes
    Route::get('/reports/sales', [ReportController::class, 'sales'])
        ->name('reports.sales');

    Route::get('/reports/outstanding', [ReportController::class, 'outstanding'])
        ->name('reports.outstanding');
});

require __DIR__.'/settings.php';
