<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
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
    Route::post('/productos/{producto}/toggle-disponibilidad', [ProductoController::class, 'toggleDisponibilidad']);
    Route::get('/productos/buscar/{codigo}', [ProductoController::class, 'buscarPorCodigo']);
    Route::get('/pos/productos/{codigo}', [ProductoController::class, 'indexPos']);
    Route::apiResource('/productos', ProductoController::class);

    // Departamentos
    Route::get('/departamentos', [DepartamentoController::class, 'index']);
    Route::get('/departamentos/all', [DepartamentoController::class, 'all']);
    Route::post('/departamentos/create', [DepartamentoController::class, 'store']);
    Route::put('/departamentos/{departamento}', [DepartamentoController::class, 'update']);
    Route::delete('/departamentos/{departamento}', [DepartamentoController::class, 'destroy']);

    // Api ventas
    Route::get('/ventas/totales-diarios', [VentaController::class, 'totalesDiarios']);
    Route::get('/ventas/totales-mensuales', [VentaController::class, 'totalesMensuales']);
    Route::get('/ventas/productos-mes', [VentaController::class, 'productosMasMenosVendidos']);
    Route::get('/ventas/buscar', [VentaController::class, 'buscar']);
    Route::apiResource('/ventas', VentaController::class);

});

// Autenticacion
Route::post('/registro', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Vista publica
Route::get('/productos-public', [ProductoController::class, 'indexPublic']);
