<?php

use App\Http\Controllers\SalesReportExportController;
use Illuminate\Support\Facades\Route;

// Public
Route::livewire('/', 'pages::shop.home')->name('home');
Route::livewire('products', 'pages::shop.products')->name('products.index');
Route::livewire('products/{product:slug}', 'pages::shop.product')->name('products.show');

// Customer
Route::middleware(['auth', 'customer'])->group(function () {
    Route::livewire('cart', 'pages::cart')->name('cart');
    Route::livewire('checkout', 'pages::checkout')->name('checkout');
    Route::livewire('orders', 'pages::orders.index')->name('orders.index');
    Route::livewire('orders/{order}', 'pages::orders.show')->name('orders.show');
    Route::livewire('orders/{order}/payment', 'pages::orders.payment')->name('orders.payment');
    Route::livewire('account', 'pages::account')->name('account');
});

// Staff back office
Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::redirect('/', '/staff/dashboard');
    Route::livewire('dashboard', 'pages::staff.dashboard')->name('dashboard');
    Route::livewire('products', 'pages::staff.products.index')->name('products.index');
    Route::livewire('products/create', 'pages::staff.products.form')->name('products.create');
    Route::livewire('products/{product}/edit', 'pages::staff.products.form')->name('products.edit');
    Route::livewire('catalog', 'pages::staff.catalog')->name('catalog');
    Route::livewire('orders', 'pages::staff.orders.index')->name('orders.index');
    Route::livewire('orders/{order}', 'pages::staff.orders.show')->name('orders.show');
    Route::livewire('users', 'pages::staff.users')->name('users');
    Route::livewire('coupons', 'pages::staff.coupons')->name('coupons');
    Route::livewire('reports', 'pages::staff.reports')->name('reports');
    Route::get('reports/export', SalesReportExportController::class)->name('reports.export');
});

require __DIR__.'/settings.php';
