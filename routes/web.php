<?php

use App\Http\Controllers\StoreSelectionController;
use App\Http\Controllers\TransactionExportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');

    Route::post('/toko/pilih', StoreSelectionController::class)->name('store.select');

    Route::livewire('/transaksi', 'pages::transactions.index')->name('transactions.index');
    Route::livewire('/transaksi/baru', 'pages::transactions.form')->name('transactions.create');
    Route::livewire('/transaksi/{transaction}', 'pages::transactions.show')->name('transactions.show');
    Route::livewire('/transaksi/{transaction}/ubah', 'pages::transactions.form')->name('transactions.edit');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/', 'pages::admin.index')->name('index');

        Route::livewire('/analisa', 'pages::admin.analytics')->name('analytics');

        Route::livewire('/pelanggan', 'pages::admin.customers.index')->name('customers.index');
        Route::livewire('/pelanggan/baru', 'pages::admin.customers.form')->name('customers.create');
        Route::livewire('/pelanggan/{customer}/ubah', 'pages::admin.customers.form')->name('customers.edit');

        Route::livewire('/pengguna', 'pages::admin.users.index')->name('users.index');
        Route::livewire('/pengguna/baru', 'pages::admin.users.form')->name('users.create');
        Route::livewire('/pengguna/{user}/ubah', 'pages::admin.users.form')->name('users.edit');

        Route::livewire('/toko', 'pages::admin.stores.index')->name('stores.index');
        Route::livewire('/toko/baru', 'pages::admin.stores.form')->name('stores.create');
        Route::livewire('/toko/{store}/ubah', 'pages::admin.stores.form')->name('stores.edit');

        Route::livewire('/produk', 'pages::admin.products.index')->name('products.index');
        Route::livewire('/produk/baru', 'pages::admin.products.form')->name('products.create');
        Route::livewire('/produk/{product}/ubah', 'pages::admin.products.form')->name('products.edit');

        Route::livewire('/kategori', 'pages::admin.categories.index')->name('categories.index');
        Route::livewire('/kategori/baru', 'pages::admin.categories.form')->name('categories.create');
        Route::livewire('/kategori/{category}/ubah', 'pages::admin.categories.form')->name('categories.edit');

        Route::livewire('/rekening', 'pages::admin.accounts.index')->name('accounts.index');
        Route::livewire('/rekening/baru', 'pages::admin.accounts.form')->name('accounts.create');
        Route::livewire('/rekening/{account}/ubah', 'pages::admin.accounts.form')->name('accounts.edit');

        Route::livewire('/unduh-riwayat', 'pages::admin.history')->name('history');
        Route::get('/unduh-riwayat/file', TransactionExportController::class)->name('history.download');
    });

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
