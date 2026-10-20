<?php

use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\Storefront\CatalogController;
use App\Http\Controllers\Api\Storefront\CustomerAddressController;
use App\Http\Controllers\Api\Storefront\FavoritoController;
use App\Http\Controllers\Api\Storefront\MercadoPagoWebhookController;
use App\Http\Controllers\Api\Storefront\OrderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\DepartamentoController;
use App\Http\Resources\UserResource;
use App\Models\Customer;
use Illuminate\Support\Facades\URL;

Route::middleware('auth:sanctum')->group(function() {

    Route::get('/user', function (Request $request) {
        return new UserResource($request->user());
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // Consulta de precios para clientes
    Route::get('/consulta/productos', [ProductoController::class, 'consulta']);

});

Route::middleware(['auth:sanctum', 'admin', 'throttle:admin'])->group(function() {

    // Api productos
    Route::get('/productos/{producto}/vencimientos', [ProductoController::class, 'obtenerVencimientos']);
    Route::get('/productos/{producto}/oferta', [ProductoController::class, 'verOferta']);
    Route::post('/productos/{producto}/toggle-disponibilidad', [ProductoController::class, 'toggleDisponibilidad']);
    Route::get('/productos/buscar/{codigo}', [ProductoController::class, 'buscarPorCodigo']);
    Route::get('/pos/productos/{codigo}', [ProductoController::class, 'indexPos']);
    Route::get('/productos/stock', [ProductoController::class, 'stockReporte']);
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
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

// Vista publica
Route::get('/productos-public', [ProductoController::class, 'indexPublic'])
    ->middleware('throttle:60,1');

// Rutas para storefront
Route::prefix('store')->group(function () {
    
    // Rutas públicas de catálogo (sin autenticación)
    Route::get('productos', [CatalogController::class, 'index']);
    Route::get('productos/{slug}', [CatalogController::class, 'show']);
    Route::get('categorias', [CatalogController::class, 'categorias']);

    Route::post('register', [CustomerAuthController::class, 'register'])
        ->middleware('throttle:login'); // reusa el rate limiter que ya tenías

    Route::post('login', [CustomerAuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::match(['get', 'post'], 'mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle']);

    Route::middleware(['auth:sanctum', 'customer'])->group(function () {
        
        Route::post('logout', [CustomerAuthController::class, 'logout']);
        Route::get('me', [CustomerAuthController::class, 'me']);
        Route::put('me', [CustomerAuthController::class, 'updateProfile']);
        Route::put('me/email', [CustomerAuthController::class, 'updateEmail']);
        Route::put('me/password', [CustomerAuthController::class, 'updatePassword']);
        
        Route::post('email/verification-notification', function (Request $request) { 
            if ($request->user()->hasVerifiedEmail()) { 
                return response()->json(
                    [ 
                        'message' => 'Tu email ya está verificado.', 
                    ], 422); 
                } 
            $redirect = $request->input('redirect', '/mi-cuenta'); 

            if (! in_array($redirect, ['/mi-cuenta', '/checkout'], true)) {
                $redirect = '/mi-cuenta';
            }

            $request->user()->sendEmailVerificationNotification($redirect);

            return response()->json([ 
                'message' => 'Te enviamos un nuevo email de verificación.', 
            ]); 
        })->middleware('throttle:6,1');
        
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        
        Route::middleware('verified')->group(function(){
            Route::post('orders', [OrderController::class, 'store']);
            Route::post('orders/{order}/pagar', [OrderController::class, 'crearPreferenciaPago']);
            Route::post('orders/{order}/cancelar', [OrderController::class, 'cancelar']);
        });

        Route::get('favoritos', [FavoritoController::class, 'index']);
        Route::post('favoritos/{productoEcommerce}', [FavoritoController::class, 'store']);
        Route::delete('favoritos/{productoEcommerce}', [FavoritoController::class, 'destroy']);

        // Direcciones del cliente
        Route::get('addresses', [CustomerAddressController::class, 'index']);
        Route::post('addresses', [CustomerAddressController::class, 'store']);
        Route::put('addresses/{id}', [CustomerAddressController::class, 'update']);
        Route::patch('addresses/{id}', [CustomerAddressController::class, 'update']);
        Route::delete('addresses/{id}', [CustomerAddressController::class, 'destroy']);

        Route::post('shipping/quote',[OrderController::class, 'cotizarEnvio']);

    });
});

// Ruta de envio de email
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) { 
    if (! URL::hasValidSignature($request)) { 
        abort(403, 'El enlace de verificación no es válido o ha expirado.'); 
    } 

    $customer = Customer::findOrFail($id); 

    if (! hash_equals(sha1($customer->email), $hash)) { 
        abort(403, 'El enlace de verificación no es válido.'); 
    } 

    $redirect = $request->query('redirect', '/mi-cuenta');

    if (! in_array($redirect, ['/mi-cuenta', '/checkout'], true)) {
        $redirect = '/mi-cuenta';
    }

    if ($customer->hasVerifiedEmail()) { 
        return redirect(
            config('app.storefront_url')
            . '/email-verificado?'
            . http_build_query([
                'estado' => 'ya-verificado',
                'redirect' => $redirect,
            ])
        ); 
    } 

    $customer->markEmailAsVerified(); 

    return redirect(
        config('app.storefront_url')
        . '/email-verificado?'
        . http_build_query([
            'estado' => 'verificado',
            'redirect' => $redirect,
        ])
    ); 
})->middleware('throttle:6,1') 
    ->name('verification.verify');

