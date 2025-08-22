<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ProductoAdminController;
use App\Http\Controllers\Admin\ProductoStockController;
use App\Http\Controllers\Admin\ProductoVencimientosController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('users.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth', 'verified')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth','verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', function() {
        return view('admin.dashboard');
    })->name('dashboard');
    Route::get('/productos/stock', [ProductoStockController::class, 'index'])->name('productos.stock');
    Route::get('/productos/vencimientos', [ProductoVencimientosController::class, 'index'])->name('productos.vencimientos');
    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('productos', ProductoAdminController::class);
    
});

require __DIR__.'/auth.php';
