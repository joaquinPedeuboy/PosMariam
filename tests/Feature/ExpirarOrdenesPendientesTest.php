<?php

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStockReservation;
use App\Models\Producto;
use App\Models\ProductoVencimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function crearOrdenConReservaParaExpiracion(
    string $numero,
    \Illuminate\Support\Carbon $expiraEn,
    OrderStatus $estado = OrderStatus::PendientePago,
): array {
    $customer = Customer::factory()->create();

    $producto = Producto::factory()->create();

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => $numero,
        'customer_id' => $customer->id,
        'estado' => $estado,
        'subtotal' => 1000,
        'total' => 1000,
        'nombre_contacto' => $customer->nombre,
        'telefono_contacto' => '1122334455',
        'email_contacto' => $customer->email,
        'direccion_envio' => 'Retiro en local',
        'ciudad' => 'Mercedes',
        'provincia' => 'Buenos Aires',
        'metodo_entrega' => 'retiro_local',
        'costo_envio' => 0,
    ]);

    $item = OrderItem::create([
        'order_id' => $orden->id,
        'producto_id' => $producto->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => 1000,
        'cantidad' => 1,
        'subtotal' => 1000,
    ]);

    $reserva = OrderStockReservation::create([
        'order_item_id' => $item->id,
        'producto_vencimiento_id' => $lote->id,
        'oferta_id' => null,
        'cantidad' => 1,
        'estado' => OrderStockReservation::ESTADO_ACTIVA,
        'expires_at' => $expiraEn,
    ]);

    return compact('orden', 'item', 'reserva', 'lote');
}

test('cancela una orden pendiente y libera la reserva cuando vencio', function () {
    $datos = crearOrdenConReservaParaExpiracion(
        'ORD-EXPIRACION001',
        now()->subMinute(),
    );

    Artisan::call('orders:expire-pending');

    expect($datos['orden']->fresh()->estado)
        ->toBe(OrderStatus::Cancelado);

    expect($datos['orden']->fresh()->cancelado_at)->not->toBeNull();

    expect($datos['reserva']->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_LIBERADA);

    expect($datos['lote']->fresh()->cantidad)->toBe(5);
});

test('no cancela una orden si su reserva todavia esta vigente', function () {
    $datos = crearOrdenConReservaParaExpiracion(
        'ORD-EXPIRACION002',
        now()->addMinutes(5),
    );

    Artisan::call('orders:expire-pending');

    expect($datos['orden']->fresh()->estado)
        ->toBe(OrderStatus::PendientePago);

    expect($datos['orden']->fresh()->cancelado_at)->toBeNull();

    expect($datos['reserva']->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);
});

test('no cancela una orden si tiene una reserva vencida y otra vigente', function () {
    $datos = crearOrdenConReservaParaExpiracion(
        'ORD-EXPIRACION003',
        now()->subMinute(),
    );

    $otraReserva = OrderStockReservation::create([
        'order_item_id' => $datos['item']->id,
        'producto_vencimiento_id' => $datos['lote']->id,
        'oferta_id' => null,
        'cantidad' => 1,
        'estado' => OrderStockReservation::ESTADO_ACTIVA,
        'expires_at' => now()->addMinutes(5),
    ]);

    Artisan::call('orders:expire-pending');

    expect($datos['orden']->fresh()->estado)
        ->toBe(OrderStatus::PendientePago);

    expect($datos['reserva']->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    expect($otraReserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);
});

test('no modifica una orden que ya no esta pendiente de pago', function () {
    $datos = crearOrdenConReservaParaExpiracion(
        'ORD-EXPIRACION004',
        now()->subMinute(),
        OrderStatus::Pagado,
    );

    Artisan::call('orders:expire-pending');

    expect($datos['orden']->fresh()->estado)
        ->toBe(OrderStatus::Pagado);

    expect($datos['reserva']->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);
});

test('ejecutar el comando dos veces no vuelve a cancelar la misma orden', function () {
    $datos = crearOrdenConReservaParaExpiracion(
        'ORD-EXPIRACION005',
        now()->subMinute(),
    );

    Artisan::call('orders:expire-pending');

    $fechaCancelacion = $datos['orden']->fresh()->cancelado_at;

    Artisan::call('orders:expire-pending');

    expect($datos['orden']->fresh()->estado)
        ->toBe(OrderStatus::Cancelado);

    expect($datos['orden']->fresh()->cancelado_at->equalTo($fechaCancelacion))
        ->toBeTrue();

    expect($datos['reserva']->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_LIBERADA);
});
