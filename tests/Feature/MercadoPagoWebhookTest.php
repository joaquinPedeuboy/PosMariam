
<?php

use App\Enums\OrderStatus;
use App\Enums\OrderShippingStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStockReservation;
use App\Models\Producto;
use App\Models\ProductoVencimiento;
use App\Services\MercadoPagoPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function firmaWebhookValida(string $dataId, string $secret, string $requestId, string $ts): string
{
    $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
    $hash = hash_hmac('sha256', $manifest, $secret);

    return "ts={$ts},v1={$hash}";
}

test('rechaza una notificacion con firma invalida', function () {
    config([
        'services.mercadopago.webhook_secret' => 'secreto-de-prueba',
    ]);

    $response = $this->postJson(
        '/api/store/mercadopago/webhook?data.id=123456',
        [
            'type' => 'payment',
            'data' => ['id' => '123456'],
        ],
        [
            'x-signature' => 'ts=123456789,v1=firma-invalida',
            'x-request-id' => 'request-de-prueba',
        ]
    );

    $response
        ->assertUnauthorized()
        ->assertJson(['message' => 'Firma inválida']);
});

test('procesa un pago aprobado y consume la reserva de stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '123456';
    $requestId = 'request-de-prueba';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0001',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $paymentDuplicado = (object) [
        'id' => '654321',
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];


    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment, $paymentDuplicado) {
            $mock->shouldReceive('obtenerPago')
                ->twice()
                ->with($paymentId)
                ->andReturn($payment);

            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with('654321')
                ->andReturn($paymentDuplicado);
        }
    );


    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson(['message' => 'Webhook procesado']);

    $responseRepetido = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                'request-repetido',
                $ts
            ),
            'x-request-id' => 'request-repetido',
        ]
    );

    $responseRepetido
        ->assertOk()
        ->assertJson(['message' => 'Ya procesado']);
    
    $responseDuplicado = $this->postJson(
        '/api/store/mercadopago/webhook?data.id=654321',
        [
            'type' => 'payment',
            'data' => ['id' => '654321'],
        ],
        [
            'x-signature' => firmaWebhookValida(
                '654321',
                $secret,
                'request-pago-duplicado',
                $ts
            ),
            'x-request-id' => 'request-pago-duplicado',
        ]
    );

    $responseDuplicado
        ->assertOk()
        ->assertJson([
            'message' => 'El pago adicional requiere conciliación manual.',
        ]);
    
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => '654321',
        'order_id' => $orden->id,
        'motivo' => 'pago_duplicado',
    ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::Pagado->value,
        'mercado_pago_payment_id' => $paymentId,
    ]);

    expect($orden->fresh()->pagado_at)->not->toBeNull()
        ->and($orden->fresh()->estado_envio)->toBe(OrderShippingStatus::Pendiente)
        ->and($lote->fresh()->cantidad)->toBe(4)
        ->and($reserva->fresh()->estado)->toBe(OrderStockReservation::ESTADO_CONSUMIDA);
});


test('rechaza un pago aprobado con importe incorrecto sin consumir stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '987654';
    $requestId = 'request-importe-incorrecto';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0002',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    // Mercado Pago informa $900, pero la orden cuesta $1000.
    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 900,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        '/api/store/mercadopago/webhook?data.id=' . $paymentId,
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk()
        ->assertJson([
            'message' => 'El importe o la moneda del pago no coinciden con la orden.',
        ]);

    // La orden sigue pendiente de pago.
    expect($orden->fresh()->estado)
        ->toBe(OrderStatus::PendientePago);

    expect($orden->fresh()->mercado_pago_payment_id)
        ->toBeNull();

    // No se descuenta stock.
    expect((int) $lote->fresh()->cantidad)->toBe(5);

    // La reserva continúa activa.
    expect($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);
});

test('rechaza un pago aprobado en moneda incorrecta sin consumir stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '987655';
    $requestId = 'request-moneda-incorrecta';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0003',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    // Mercado Pago informa $900, pero la orden cuesta $1000.
    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'USD', // Moneda incorrecta
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        '/api/store/mercadopago/webhook?data.id=' . $paymentId,
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk()
        ->assertJson([
            'message' => 'El importe o la moneda del pago no coinciden con la orden.',
        ]);

    // La orden sigue pendiente de pago.
    expect($orden->fresh()->estado)
        ->toBe(OrderStatus::PendientePago);

    expect($orden->fresh()->mercado_pago_payment_id)
        ->toBeNull();

    // No se descuenta stock.
    expect((int) $lote->fresh()->cantidad)->toBe(5);

    // La reserva continúa activa.
    expect($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);
});


test('ignora un pago aprobado cuya orden no existe', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '777888';
    $requestId = 'request-orden-inexistente';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => 'ORD-INEXISTENTE',
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'Orden no encontrada',
        ]);

    $this->assertDatabaseMissing('orders', [
        'numero' => 'ORD-INEXISTENTE',
    ]);

    $this->assertDatabaseMissing('orders', [
        'mercado_pago_payment_id' => $paymentId,
    ]);
});


test('no permite que un mismo pago confirme dos órdenes diferentes', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '123456';
    $requestId = 'request-misma-pago-dos-ordenes';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    // Primera orden: ya tiene asociado el pago.
    $ordenOriginal = Order::create([
        'numero' => 'ORD-TEST0001',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::Pagado,
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
        'mercado_pago_payment_id' => $paymentId,
        'pagado_at' => now(),
    ]);

    // Segunda orden: todavía está pendiente de pago.
    $ordenNueva = Order::create([
        'numero' => 'ORD-TEST0002',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'order_id' => $ordenNueva->id,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    // El mismo pago declara pertenecer a la segunda orden.
    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $ordenNueva->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'El pago requiere conciliación manual.',
        ]);

    // La orden original conserva el pago.
    $this->assertDatabaseHas('orders', [
        'id' => $ordenOriginal->id,
        'mercado_pago_payment_id' => $paymentId,
        'estado' => OrderStatus::Pagado->value,
    ]);

    // La segunda orden no debe confirmarse.
    $this->assertDatabaseHas('orders', [
        'id' => $ordenNueva->id,
        'mercado_pago_payment_id' => null,
        'estado' => OrderStatus::PendientePago->value,
    ]);

    // No se consume stock ni se altera la reserva.
    expect($lote->fresh()->cantidad)->toBe(5)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    // Se registra la incidencia para revisión manual.
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentId,
        'order_id' => $ordenOriginal->id,
        'motivo' => 'pago_asociado_a_otra_orden',
    ]);
});


test('registra para conciliacion un pago aprobado recibido despues de cancelar la orden', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789123';
    $requestId = 'request-pago-tardio';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0003',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::Cancelado,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'El pago requiere conciliación manual.',
        ]);

    // La orden sigue cancelada y el pago no se asocia automáticamente.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::Cancelado->value,
        'mercado_pago_payment_id' => null,
    ]);

    // No se descuenta stock ni se consume la reserva.
    expect($lote->fresh()->cantidad)->toBe(5)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    // El pago tardío queda registrado para conciliación manual.
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentId,
        'order_id' => $orden->id,
        'motivo' => 'pago_tardio_cancelado',
    ]);
});


test('un pago rechazado marca la orden como fallida y libera las reservas sin consumir stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789124';
    $requestId = 'request-pago-rechazado';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0004',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'rejected',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'El pago fue rechazado. Las reservas de stock se liberaron; generá una nueva orden para volver a intentarlo.',
        ]);

    // La orden queda fallida y registra el pago rechazado.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::Fallido->value,
        'mercado_pago_payment_id' => $paymentId,
    ]);

    // La reserva se libera, pero el lote conserva su cantidad.
    expect($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_LIBERADA)
        ->and($lote->fresh()->cantidad)->toBe(5);
});


test('un pago pendiente no confirma la orden ni modifica las reservas de stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789125';
    $requestId = 'request-pago-pendiente';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0005',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'pending',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response
        ->assertOk()
        ->assertJson([
            'message' => 'El pago todavía no está aprobado ni rechazado.',
        ]);

    // La orden sigue pendiente y no se asocia el pago todavía.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    // La reserva continúa activa y el stock no cambia.
    expect($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA)
        ->and($lote->fresh()->cantidad)->toBe(5);
});


test('un pago aprobado con una reserva vencida no consume stock y requiere conciliacion', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789126';
    $requestId = 'request-reserva-vencida';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0006',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->subMinute(),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk();

    // La orden no debe quedar confirmada.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    // La transacción debe revertir cualquier descuento de stock.
    expect($lote->fresh()->cantidad)->toBe(5)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    // El pago debe quedar registrado para revisión manual.
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentId,
        'order_id' => $orden->id,
        'motivo' => 'pago_aprobado_no_aplicado',
    ]);
});


test('un pago aprobado con stock insuficiente no confirma la orden y requiere conciliacion', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789127';
    $requestId = 'request-stock-insuficiente';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000001',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    // El lote ya no tiene unidades disponibles.
    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 0,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0007',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk();

    // La orden no debe confirmarse ni asociarse al pago.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    // No debe haber descuento parcial ni stock negativo.
    expect($lote->fresh()->cantidad)->toBe(0)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    // La incidencia debe quedar disponible para conciliación manual.
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentId,
        'order_id' => $orden->id,
        'motivo' => 'pago_aprobado_no_aplicado',
    ]);
});


test('revierte el consumo de todos los productos si falla una reserva del pedido', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789128';
    $requestId = 'request-rollback-stock';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $productoA = Producto::create([
        'nombre' => 'Producto A',
        'codigo_barras' => 'TEST000000002',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $productoB = Producto::create([
        'nombre' => 'Producto B',
        'codigo_barras' => 'TEST000000003',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    // El primer lote tiene stock suficiente.
    $loteA = ProductoVencimiento::create([
        'producto_id' => $productoA->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    // El segundo lote no tiene stock suficiente.
    $loteB = ProductoVencimiento::create([
        'producto_id' => $productoB->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 0,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0008',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
        'subtotal' => 2000,
        'total' => 2000,
        'nombre_contacto' => $customer->nombre,
        'telefono_contacto' => '1122334455',
        'email_contacto' => $customer->email,
        'direccion_envio' => 'Retiro en local',
        'ciudad' => 'Mercedes',
        'provincia' => 'Buenos Aires',
        'metodo_entrega' => 'retiro_local',
        'costo_envio' => 0,
    ]);

    $itemA = OrderItem::create([
        'order_id' => $orden->id,
        'producto_id' => $productoA->id,
        'nombre_producto' => $productoA->nombre,
        'precio_unitario' => 1000,
        'cantidad' => 1,
        'subtotal' => 1000,
    ]);

    $itemB = OrderItem::create([
        'order_id' => $orden->id,
        'producto_id' => $productoB->id,
        'nombre_producto' => $productoB->nombre,
        'precio_unitario' => 1000,
        'cantidad' => 1,
        'subtotal' => 1000,
    ]);

    $reservaA = OrderStockReservation::create([
        'order_item_id' => $itemA->id,
        'producto_vencimiento_id' => $loteA->id,
        'oferta_id' => null,
        'cantidad' => 1,
        'estado' => OrderStockReservation::ESTADO_ACTIVA,
        'expires_at' => now()->addMinutes(15),
    ]);

    $reservaB = OrderStockReservation::create([
        'order_item_id' => $itemB->id,
        'producto_vencimiento_id' => $loteB->id,
        'oferta_id' => null,
        'cantidad' => 1,
        'estado' => OrderStockReservation::ESTADO_ACTIVA,
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 2000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk();

    // La orden no se confirma.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    // El primer lote también debe recuperar su stock original.
    expect((int) $loteA->fresh()->cantidad)->toBe(5)
        ->and((int) $loteB->fresh()->cantidad)->toBe(0)
        ->and($reservaA->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA)
        ->and($reservaB->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    // No debe quedar ningún movimiento parcial de stock.
    $this->assertDatabaseMissing('order_item_stock_movements', [
        'order_item_id' => $itemA->id,
    ]);

    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentId,
        'order_id' => $orden->id,
        'motivo' => 'pago_aprobado_no_aplicado',
    ]);
});


test('un mismo pago aprobado recibido dos veces consume el stock una sola vez', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789129';
    $requestId = 'request-webhook-repetido';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000004',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0009',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $payment = (object) [
        'id' => $paymentId,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    // Mercado Pago se consulta en cada notificación recibida.
    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId, $payment) {
            $mock->shouldReceive('obtenerPago')
                ->twice()
                ->with($paymentId)
                ->andReturn($payment);
        }
    );

    $payload = [
        'type' => 'payment',
        'data' => ['id' => $paymentId],
    ];

    $headers = [
        'x-signature' => firmaWebhookValida(
            $paymentId,
            $secret,
            $requestId,
            $ts
        ),
        'x-request-id' => $requestId,
    ];

    // Primera notificación: procesa el pago.
    $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        $payload,
        $headers
    )->assertOk();

    // Segunda notificación idéntica: no debe repetir el consumo.
    $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        $payload,
        $headers
    )->assertOk();

    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::Pagado->value,
        'mercado_pago_payment_id' => $paymentId,
    ]);

    expect((int) $lote->fresh()->cantidad)->toBe(4)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_CONSUMIDA);

    // Solo debe existir un movimiento para este artículo.
    $this->assertDatabaseCount('order_item_stock_movements', 1);
});


test('un segundo pago aprobado para una orden pagada requiere conciliacion sin consumir stock otra vez', function () {
    $secret = 'secreto-de-prueba';
    $paymentIdInicial = '789130';
    $paymentIdDuplicado = '789131';
    $requestIdInicial = 'request-primer-pago';
    $requestIdDuplicado = 'request-segundo-pago';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000005',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0010',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $pagoInicial = (object) [
        'id' => $paymentIdInicial,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $pagoDuplicado = (object) [
        'id' => $paymentIdDuplicado,
        'external_reference' => $orden->numero,
        'status' => 'approved',
        'currency_id' => 'ARS',
        'transaction_amount' => 1000,
    ];

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use (
            $paymentIdInicial,
            $paymentIdDuplicado,
            $pagoInicial,
            $pagoDuplicado
        ) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentIdInicial)
                ->andReturn($pagoInicial);

            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentIdDuplicado)
                ->andReturn($pagoDuplicado);
        }
    );

    // Primer pago: confirma la orden y consume una unidad.
    $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentIdInicial}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentIdInicial],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentIdInicial,
                $secret,
                $requestIdInicial,
                $ts
            ),
            'x-request-id' => $requestIdInicial,
        ]
    )->assertOk();

    // Segundo pago distinto: debe requerir conciliación manual.
    $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentIdDuplicado}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentIdDuplicado],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentIdDuplicado,
                $secret,
                $requestIdDuplicado,
                $ts
            ),
            'x-request-id' => $requestIdDuplicado,
        ]
    )->assertOk()
        ->assertJson([
            'message' => 'El pago adicional requiere conciliación manual.',
        ]);

    // La orden conserva el primer pago registrado.
    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::Pagado->value,
        'mercado_pago_payment_id' => $paymentIdInicial,
    ]);

    // No se vuelve a consumir stock.
    expect((int) $lote->fresh()->cantidad)->toBe(4)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_CONSUMIDA);

    $this->assertDatabaseCount('order_item_stock_movements', 1);

    // El segundo pago queda registrado con motivo de pago duplicado.
    $this->assertDatabaseHas('mercado_pago_conciliaciones', [
        'payment_id' => $paymentIdDuplicado,
        'order_id' => $orden->id,
        'motivo' => 'pago_duplicado',
    ]);
});


test('un error al consultar Mercado Pago no confirma la orden ni modifica el stock', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789132';
    $requestId = 'request-error-consulta';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000006',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0011',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) use ($paymentId) {
            $mock->shouldReceive('obtenerPago')
                ->once()
                ->with($paymentId)
                ->andThrow(new \RuntimeException(
                    'Error simulado de conexión con Mercado Pago.'
                ));
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk()
        ->assertJson([
            'message' => 'Error al verificar el pago',
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    expect((int) $lote->fresh()->cantidad)->toBe(5)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    $this->assertDatabaseMissing('order_item_stock_movements', [
        'order_item_id' => $item->id,
    ]);
});


test('ignora notificaciones que no corresponden a pagos', function () {
    $secret = 'secreto-de-prueba';
    $paymentId = '789133';
    $requestId = 'request-tipo-incorrecto';
    $ts = '123456789';

    config([
        'services.mercadopago.webhook_secret' => $secret,
        'services.mercadopago.access_token' => 'TEST-token',
    ]);

    $customer = Customer::factory()->create();

    $producto = Producto::create([
        'nombre' => 'Producto de prueba',
        'codigo_barras' => 'TEST000000007',
        'precio' => 1000,
        'departamento_id' => null,
    ]);

    $lote = ProductoVencimiento::create([
        'producto_id' => $producto->id,
        'fecha_vencimiento' => '2027-12',
        'cantidad' => 5,
    ]);

    $orden = Order::create([
        'numero' => 'ORD-TEST0012',
        'customer_id' => $customer->id,
        'estado' => OrderStatus::PendientePago,
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
        'expires_at' => now()->addMinutes(15),
    ]);

    // El servicio no debe consultarse para una notificación ajena a pagos.
    $this->mock(
        MercadoPagoPaymentService::class,
        function ($mock) {
            $mock->shouldNotReceive('obtenerPago');
        }
    );

    $response = $this->postJson(
        "/api/store/mercadopago/webhook?data.id={$paymentId}",
        [
            'type' => 'merchant_order',
            'data' => ['id' => $paymentId],
        ],
        [
            'x-signature' => firmaWebhookValida(
                $paymentId,
                $secret,
                $requestId,
                $ts
            ),
            'x-request-id' => $requestId,
        ]
    );

    $response->assertOk()
        ->assertJson([
            'message' => 'Notificación ignorada',
        ]);

    $this->assertDatabaseHas('orders', [
        'id' => $orden->id,
        'estado' => OrderStatus::PendientePago->value,
        'mercado_pago_payment_id' => null,
    ]);

    expect((int) $lote->fresh()->cantidad)->toBe(5)
        ->and($reserva->fresh()->estado)
        ->toBe(OrderStockReservation::ESTADO_ACTIVA);

    $this->assertDatabaseMissing('order_item_stock_movements', [
        'order_item_id' => $item->id,
    ]);
});
