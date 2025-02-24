<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\DepartamentoController;

Route::middleware('auth:sanctum')->group(function() {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Cierre de sesion
    Route::post('/logout', [AuthController::class, 'logout']);

    // Api productos
    Route::get('/productos/{producto}/vencimientos', [ProductoController::class, 'obtenerVencimientos']);
    Route::get('/productos/{producto}/oferta', [ProductoController::class, 'verOferta']);
    Route::apiResource('/productos', ProductoController::class);

    // Departamentos
    Route::get('/departamentos', [DepartamentoController::class, 'index']);
    Route::post('/departamentos/create', [DepartamentoController::class, 'store']);

    // Api ventas
    Route::get('/ventas/totales-diarios', [VentaController::class, 'totalesDiarios']);
    Route::get('/ventas/totales-mensuales', [VentaController::class, 'totalesMensuales']);
    Route::get('/ventas/productos-mes', [VentaController::class, 'productosMasMenosVendidos']);
    Route::get('/ventas/buscar', [VentaController::class, 'buscar']);
    Route::apiResource('/ventas', VentaController::class);

});

Route::delete('/imagenes/{imagen}', [ImagenController::class, 'destroy']);


// Autenticacion
Route::post('/registro', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

