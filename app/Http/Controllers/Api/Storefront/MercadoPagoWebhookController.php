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
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

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
            $payment = (new PaymentClient())->get($paymentId);
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

        // Idempotencia (atajo rápido, sin bloquear filas): si ya sabemos
        // que está pagado, ni abrimos la transacción. OJO: esto NO es la
        // única protección — el chequeo real y confiable está adentro de
        // la transacción de abajo, con el registro bloqueado.
        if ($order->estado === OrderStatus::Pagado) {
            return response()->json(['message' => 'Ya procesado'], 200);
        }

        DB::transaction(function () use ($order, $payment) {
            // Releemos el pedido con lockForUpdate DENTRO de la
            // transacción: si otro webhook casi simultáneo ya lo procesó
            // entre el chequeo de arriba y este punto, o si el pedido fue
            // cancelado mientras tanto, esto lo detecta con el dato más
            // actualizado posible y bloqueado contra otras escrituras.
            //
            // Usamos lista blanca (solo se puede avanzar desde
            // pendiente_pago o fallido) en vez de lista negra (bloquear
            // solo si ya está pagado) — así un pedido cancelado nunca se
            // puede "revivir" con un webhook que llega tarde.
            $ordenBloqueada = Order::whereKey($order->id)->lockForUpdate()->first();

            if (!$ordenBloqueada || !in_array($ordenBloqueada->estado, [
                OrderStatus::PendientePago,
                OrderStatus::Fallido,
            ], true)) {
                return;
            }

            $ordenBloqueada->mercado_pago_payment_id = (string) $payment->id;

            match ($payment->status) {
                'approved' => $this->marcarComoPagado($ordenBloqueada),
                'rejected' => $ordenBloqueada->estado = OrderStatus::Fallido,
                default => null, // pending / in_process → se queda como estaba
            };

            $ordenBloqueada->save();
        });

        return response()->json(['message' => 'Webhook procesado'], 200);
    }

    private function marcarComoPagado(Order $order): void
    {
        $order->estado = OrderStatus::Pagado;
        $order->pagado_at = now();
        $order->estado_envio = OrderShippingStatus::Pendiente;

        foreach ($order->items as $item) {
            if ($item->producto_id) {
                $this->descontarStock($item->producto_id, $item->cantidad);
            }
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

        // Formato legacy (topic=payment / topic=merchant_order): no trae firma, lo dejamos pasar
        // y confiamos en la re-verificación contra la API con el Access Token.
        if (!$secret || !$xSignature || !$xRequestId) {
            return true;
        }

        $dataId = $request->input('data.id') ?? $request->query('id');

        if (!$dataId) {
            return false;
        }

        $parts = collect(explode(',', $xSignature))
            ->mapWithKeys(function ($part) {
                [$key, $value] = array_map('trim', explode('=', $part, 2));
                return [$key => $value];
            });

        $ts = $parts->get('ts');
        $hash = $parts->get('v1');

        $manifest = "id:{$dataId};request-id:{$xRequestId};ts:{$ts};";
        $hmac = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($hmac, $hash);
    }
}
