<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\OrderShippingStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStockMovement;
use App\Models\ProductoVencimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\MercadoPagoConfig;
use App\Models\Oferta;
use App\Models\OrderStockReservation;
use App\Models\MercadoPagoConciliacion;
use App\Services\MercadoPagoPaymentService;

class MercadoPagoWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (!$this->firmaValida($request)) {
            \Log::warning('Webhook de Mercado Pago con firma inválida', $request->all());
            return response()->json(['message' => 'Firma inválida'], 401);
        }

        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));

        $type = $request->input('type') ?? $request->input('topic');
        $paymentId = $request->input('data.id') ?? $request->input('id');

        if ($type !== 'payment' || !$paymentId) {
            return response()->json(['message' => 'Notificación ignorada'], 200);
        }

        try {
            $payment = app(MercadoPagoPaymentService::class)->obtenerPago($paymentId);
        } catch (\Throwable $e) {
            Log::error('Error consultando pago en Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error al verificar el pago'], 200);
        }

        $order = Order::where('numero', $payment->external_reference)->first();

        if (!$order) {
            Log::warning('Webhook de Mercado Pago para una orden inexistente', [
                'external_reference' => $payment->external_reference,
            ]);
            return response()->json(['message' => 'Orden no encontrada'], 200);
        }

        // Validar importe y moneda estrictamente para pagos aprobados.
        // Los pagos rechazados deben poder liberar sus reservas aunque
        // los datos del importe estén incompletos o no coincidan.
        if ($payment->status === 'approved') {
            $monedaPago = $payment->currency_id ?? null;
            $importePago = $payment->transaction_amount ?? null;

            $importeOrdenCentavos = (int) round(
                (float) $order->total * 100
            );

            $importePagoCentavos = $importePago !== null
                ? (int) round((float) $importePago * 100)
                : null;

            if (
                $monedaPago !== 'ARS'
                || $importePagoCentavos === null
                || $importePagoCentavos !== $importeOrdenCentavos
            ) {
                $this->registrarConciliacion(
                    $order,
                    $payment,
                    'importe_incorrecto'
                );

                Log::warning(
                    'Pago aprobado de Mercado Pago con importe o moneda incorrectos',
                    [
                        'order_id' => $order->id,
                        'order_number' => $order->numero,
                        'payment_id' => (string) $payment->id,
                        'currency_received' => $monedaPago,
                        'amount_received' => $importePago,
                        'currency_expected' => 'ARS',
                        'amount_expected' => (float) $order->total,
                    ]
                );

                return response()->json([
                    'message' => 'El importe o la moneda del pago no coinciden con la orden.',
                ], 200);
            }
        }

        try {
            $resultado = DB::transaction(function () use ($order, $payment) {
                $ordenBloqueada = Order::whereKey($order->id)
                    ->lockForUpdate()
                    ->first();

                if (!$ordenBloqueada) {
                    return 'orden_inexistente';
                }

                // Un mismo pago no puede confirmar dos órdenes diferentes.
                $otraOrden = Order::query()
                    ->where('mercado_pago_payment_id', (string) $payment->id)
                    ->where('id', '!=', $ordenBloqueada->id)
                    ->lockForUpdate()
                    ->first();

                if ($otraOrden) {
                    $this->registrarConciliacion(
                        $otraOrden,
                        $payment,
                        'pago_asociado_a_otra_orden'
                    );

                    Log::critical(
                        'El pago de Mercado Pago ya está asociado a otra orden.',
                        [
                            'payment_id' => (string) $payment->id,
                            'order_id_actual' => $ordenBloqueada->id,
                            'order_id_asociado' => $otraOrden->id,
                        ]
                    );

                    return 'pago_requiere_conciliacion';
                }

                if ($ordenBloqueada->estado === OrderStatus::Pagado) {
                    $paymentIdActual = (string) $payment->id;
                    $paymentIdRegistrado = (string) (
                        $ordenBloqueada->mercado_pago_payment_id ?? ''
                    );

                    if (
                        $payment->status === 'approved'
                        && $paymentIdRegistrado !== ''
                        && $paymentIdRegistrado !== $paymentIdActual
                    ) {
                        $this->registrarConciliacion(
                            $ordenBloqueada,
                            $payment,
                            'pago_duplicado'
                        );

                        return 'pago_duplicado';
                    }

                    return 'ya_procesado';
                }

                if ($ordenBloqueada->estado !== OrderStatus::PendientePago) {
                    if ($payment->status === 'approved') {
                        $motivo = match ($ordenBloqueada->estado) {
                            OrderStatus::Cancelado => 'pago_tardio_cancelado',
                            OrderStatus::Fallido => 'pago_tardio_fallido',
                            default => 'orden_no_habilitada',
                        };

                        $this->registrarConciliacion(
                            $ordenBloqueada,
                            $payment,
                            $motivo
                        );

                        return 'pago_requiere_conciliacion';
                    }

                    return 'orden_no_habilitada';
                }

                if ($payment->status === 'approved') {
                    $ordenBloqueada->load('items');

                    // Verifica y consume las reservas dentro de esta transacción.
                    // Si falta stock o alguna reserva venció, se revierte todo.
                    $this->marcarComoPagado($ordenBloqueada);

                    $ordenBloqueada->mercado_pago_payment_id =
                        (string) $payment->id;

                    $ordenBloqueada->save();

                    return 'procesado';
                }

                if ($payment->status === 'rejected') {
                    $reservasActivas = OrderStockReservation::query()
                        ->whereHas('orderItem', function ($query) use ($ordenBloqueada) {
                            $query->where('order_id', $ordenBloqueada->id);
                        })
                        ->where('estado', OrderStockReservation::ESTADO_ACTIVA)
                        ->lockForUpdate()
                        ->get();

                    foreach ($reservasActivas as $reserva) {
                        $reserva->update([
                            'estado' => OrderStockReservation::ESTADO_LIBERADA,
                        ]);
                    }

                    $ordenBloqueada->estado = OrderStatus::Fallido;
                    $ordenBloqueada->mercado_pago_payment_id =
                        (string) $payment->id;

                    $ordenBloqueada->save();

                    return 'pago_rechazado';
                }

                // Los estados pendientes u otros estados de Mercado Pago
                // no deben modificar el stock ni confirmar la orden.
                return 'pago_no_finalizado';
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $esDuplicadoMySQL =
                (int) ($e->errorInfo[1] ?? 0) === 1062;

            Log::critical('Error de base de datos al procesar un pago de Mercado Pago.', [
                'order_id' => $order->id,
                'numero_orden' => $order->numero,
                'payment_id' => $payment->id ?? null,
                'payment_status' => $payment->status ?? null,
                'codigo_mysql' => $e->errorInfo[1] ?? null,
                'error' => $e->getMessage(),
            ]);

            if ($esDuplicadoMySQL && ($payment->status ?? null) === 'approved') {
                try {
                    $ordenConPago = Order::query()
                        ->where('mercado_pago_payment_id', (string) $payment->id)
                        ->first();

                    if ($ordenConPago && $ordenConPago->id !== $order->id) {
                        $this->registrarConciliacion(
                            $ordenConPago,
                            $payment,
                            'pago_asociado_a_otra_orden'
                        );

                        Log::critical(
                            'Se detectó un pago aprobado asociado a otra orden.',
                            [
                                'payment_id' => (string) $payment->id,
                                'order_id_actual' => $order->id,
                                'order_id_asociado' => $ordenConPago->id,
                            ]
                        );
                    } else {
                        $ordenActualizada = $order->fresh() ?? $order;

                        $this->registrarConciliacion(
                            $ordenActualizada,
                            $payment,
                            'pago_aprobado_no_aplicado'
                        );
                    }
                } catch (\Throwable $errorRegistro) {
                    Log::critical(
                        'No se pudo registrar la conciliación tras un error de base de datos.',
                        [
                            'order_id' => $order->id,
                            'payment_id' => $payment->id ?? null,
                            'error' => $errorRegistro->getMessage(),
                        ]
                    );
                }
            }
            return response()->json(['received' => true], 200);

        } catch (\RuntimeException $e) {
            Log::critical('Pago recibido que requiere revisión manual.', [
                'order_id' => $order->id,
                'numero_orden' => $order->numero,
                'payment_id' => $payment->id ?? null,
                'payment_status' => $payment->status ?? null,
                'error' => $e->getMessage(),
            ]);

            if (($payment->status ?? null) === 'approved') {
                try {
                    $ordenActualizada = $order->fresh() ?? $order;

                    $this->registrarConciliacion(
                        $ordenActualizada,
                        $payment,
                        'pago_aprobado_no_aplicado'
                    );
                } catch (\Throwable $errorRegistro) {
                    Log::critical(
                        'No se pudo guardar la conciliación de un pago aprobado.',
                        [
                            'order_id' => $order->id,
                            'payment_id' => $payment->id ?? null,
                            'error' => $errorRegistro->getMessage(),
                        ]
                    );
                }
            }

            return response()->json(['received' => true], 200);
        }

        if ($resultado === 'ya_procesado') {
            return response()->json([
                'message' => 'Ya procesado',
            ], 200);
        }

        if ($resultado === 'orden_no_habilitada') {
            Log::critical('Notificación de pago para una orden no habilitada', [
                'order_id' => $order->id,
                'order_number' => $order->numero,
                'payment_id' => (string) $payment->id,
                'payment_status' => $payment->status,
            ]);

            return response()->json([
                'message' => 'La orden requiere conciliación.',
            ], 200);
        }

        if ($resultado === 'pago_rechazado') {
            return response()->json([
                'message' => 'El pago fue rechazado. Las reservas de stock se liberaron; generá una nueva orden para volver a intentarlo.',
            ], 200);
        }

        if ($resultado === 'pago_no_finalizado') {
            return response()->json([
                'message' => 'El pago todavía no está aprobado ni rechazado.',
            ], 200);
        }

        if ($resultado === 'orden_inexistente') {
            return response()->json([
                'message' => 'Orden no encontrada.',
            ], 200);
        }

        if ($resultado === 'pago_duplicado') {
            Log::critical('Pago aprobado adicional para una orden ya pagada', [
                'order_id' => $order->id,
                'payment_id' => (string) $payment->id,
            ]);

            return response()->json([
                'message' => 'El pago adicional requiere conciliación manual.',
            ], 200);
        }

        if ($resultado === 'pago_requiere_conciliacion') {
            Log::critical('Pago aprobado para una orden no habilitada', [
                'order_id' => $order->id,
                'payment_id' => (string) $payment->id,
                'payment_status' => $payment->status,
            ]);

            return response()->json([
                'message' => 'El pago requiere conciliación manual.',
            ], 200);
        }

        return response()->json([
            'message' => 'Webhook procesado',
        ], 200);
    }

    private function marcarComoPagado(Order $order): void
    {
        foreach ($order->items as $item) {
            if (!$item->producto_id) {
                throw new \RuntimeException(
                    "El artículo {$item->id} no tiene un producto asociado."
                );
            }

            $this->consumirReservas($item);
        }

        $order->estado = OrderStatus::Pagado;
        $order->pagado_at = now();
        $order->estado_envio = OrderShippingStatus::Pendiente;
    }

    /**
     * Consume las reservas exactas del pedido.
     *
     * Debe ejecutarse dentro de una transacción que bloquee
     * previamente la orden.
     */
    private function consumirReservas(OrderItem $item): void
    {
        $reservas = OrderStockReservation::query()
            ->where('order_item_id', $item->id)
            ->where('estado', OrderStockReservation::ESTADO_ACTIVA)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $cantidadReservada = (int) $reservas->sum('cantidad');

        if ($cantidadReservada !== (int) $item->cantidad) {
            throw new \RuntimeException(
                "Las reservas del artículo {$item->id} no coinciden con la cantidad del pedido."
            );
        }

        foreach ($reservas as $reserva) {
            if (
                $reserva->expires_at !== null
                && !$reserva->expires_at->isFuture()
            ) {
                throw new \RuntimeException(
                    "La reserva {$reserva->id} ya venció."
                );
            }

            if ($reserva->oferta_id !== null) {
                $oferta = Oferta::query()
                    ->whereKey($reserva->oferta_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$oferta
                    || (int) $oferta->cantidad < (int) $reserva->cantidad
                ) {
                    throw new \RuntimeException(
                        "No hay stock suficiente en la oferta reservada {$reserva->oferta_id}."
                    );
                }

                $oferta->decrement('cantidad', $reserva->cantidad);
            } elseif ($reserva->producto_vencimiento_id !== null) {
                $lote = ProductoVencimiento::query()
                    ->whereKey($reserva->producto_vencimiento_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$lote
                    || (int) $lote->cantidad < (int) $reserva->cantidad
                ) {
                    throw new \RuntimeException(
                        "No hay stock suficiente en el lote reservado {$reserva->producto_vencimiento_id}."
                    );
                }

                $lote->decrement('cantidad', $reserva->cantidad);

                OrderItemStockMovement::create([
                    'order_item_id' => $item->id,
                    'producto_vencimiento_id' => $lote->id,
                    'cantidad' => $reserva->cantidad,
                ]);
            } else {
                throw new \RuntimeException(
                    "La reserva {$reserva->id} no tiene una fuente de stock válida."
                );
            }

            $reserva->update([
                'estado' => OrderStockReservation::ESTADO_CONSUMIDA,
            ]);
        }
    }

    /**
     * Descuenta stock por FEFO (primero lo que vence antes) y deja
     * registrado en order_item_stock_movements de qué lote salió cada
     * cantidad, para poder reponerlo con precisión si el pedido se cancela.
     */
    private function descontarStock(OrderItem $item): void
    {
        $restante = $item->cantidad;

        $vencimientos = ProductoVencimiento::where('producto_id', $item->producto_id)
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento') // FEFO: se descuenta primero lo que vence antes
            ->lockForUpdate()
            ->get();

        foreach ($vencimientos as $vencimiento) {
            if ($restante <= 0) break;

            $descuento = min($vencimiento->cantidad, $restante);
            $vencimiento->decrement('cantidad', $descuento);

            OrderItemStockMovement::create([
                'order_item_id' => $item->id,
                'producto_vencimiento_id' => $vencimiento->id,
                'cantidad' => $descuento,
            ]);
            
            $restante -= $descuento;
        }

        if ($restante > 0) {
            Log::warning("Stock insuficiente al confirmar pago para producto_id {$item->producto_id}", [
                'faltante' => $restante,
                'order_item_id' => $item->id,
            ]);

            // Queda visible en el admin (pantalla de pedidos) cuál item
            // puntual y cuántas unidades no se pudieron cubrir, para que
            // no dependa de que alguien vaya a revisar los logs.
            $item->faltante = $restante;
            $item->save();
        }
    }

    private function firmaValida(Request $request): bool
    {
        $secret = config('services.mercadopago.webhook_secret');
        $xSignature = $request->header('x-signature');
        $xRequestId = $request->header('x-request-id');

        // Si no está configurado el secreto, rechazamos la notificación.
        if (
            !is_string($secret)
            || trim($secret) === ''
            || !is_string($xSignature)
            || trim($xSignature) === ''
            || !is_string($xRequestId)
            || trim($xRequestId) === ''
        ) {
            return false;
        }

        $query = $request->query();

        $dataId = $query['data_id']
            ?? $query['data.id']
            ?? $query['id']
            ?? null;

        if (!is_scalar($dataId) || (string) $dataId === '') {
            return false;
        }

        $ts = null;
        $hash = null;

        foreach (explode(',', $xSignature) as $part) {
            $segmentos = explode('=', trim($part), 2);

            if (count($segmentos) !== 2) {
                continue;
            }

            [$key, $value] = array_map('trim', $segmentos);

            if ($key === 'ts') {
                $ts = $value;
            } elseif ($key === 'v1') {
                $hash = $value;
            }
        }

        if (
            !is_string($ts)
            || $ts === ''
            || !is_string($hash)
            || !preg_match('/^[a-fA-F0-9]{64}$/', $hash)
        ) {
            return false;
        }

        // Mercado Pago exige el data.id en minúsculas dentro del manifiesto.
        $dataId = strtolower((string) $dataId);

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";

        $expected = hash_hmac(
            'sha256',
            $manifest,
            $secret
        );

        return hash_equals($expected, strtolower($hash));
    }

    private function registrarConciliacion(
        Order $order,
        object $payment,
        string $motivo
    ): void {
        MercadoPagoConciliacion::firstOrCreate(
            [
                'payment_id' => (string) $payment->id,
            ],
            [
                'order_id' => $order->id,
                'external_reference' => $payment->external_reference
                    ?? $order->numero,
                'payment_status' => $payment->status ?? null,
                'motivo' => $motivo,
                'importe_recibido' => $payment->transaction_amount
                    ?? null,
                'moneda_recibida' => $payment->currency_id ?? null,
                'detalles' => [
                    'estado_orden' => $order->estado->value,
                    'numero_orden' => $order->numero,
                ],
            ]
        );
    }
}
