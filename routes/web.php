<?php

use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\ProductAdminController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ImagenController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('dashboard');

    Route::get('products/buscar-pos', [ProductAdminController::class, 'buscarProductoPos'])
    ->name('products.buscar-pos');
    Route::resource('products', ProductAdminController::class)
        ->parameters(['products' => 'productoEcommerce']);
    Route::resource('categories', CategoryAdminController::class)
    ->parameters(['categories' => 'category']);
    Route::delete('images/{imagen}', [ImagenController::class, 'destroy'])->name('images.destroy');
    Route::get('orders', [OrderAdminController::class, 'index'])->name('admin.orders.index');
    Route::get('orders/{order}', [OrderAdminController::class, 'show'])->name('admin.orders.show');
    Route::patch('orders/{order}/cancelar', [OrderAdminController::class, 'cancelar'])->name('admin.orders.cancelar');
    Route::patch('orders/{order}/envio', [OrderAdminController::class, 'actualizarEnvio'])->name('admin.orders.envio.update');
});

// Login (invitados)
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login'); // reusa el rate limiter que ya existe en AppServiceProvider
});

Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');