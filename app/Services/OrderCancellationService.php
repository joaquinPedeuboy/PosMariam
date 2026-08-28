<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItemStockMovement;
use App\Models\ProductoVencimiento;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    /**
     * Reglas de cuándo un pedido puede cancelarse. Las usan tanto el admin
     * como el endpoint del cliente en el storefront, para que ambos lados
     * apliquen exactamente la misma regla y no se desincronicen.
     */
    public function puedeCancelarse(Order $order): bool
    {
        $estadoCancelable = in_array($order->estado->value, ['pendiente_pago', 'pagado'], true);

        $envioYaEnCurso = $order->estado_envio
            && in_array($order->estado_envio->value, ['enviado', 'entregado'], true);

        return $estadoCancelable && !$envioYaEnCurso;
    }

    /**
     * Cancela el pedido. Si estaba pagado, repone el stock exacto a los
     * lotes de donde salió (usando order_item_stock_movements).
     *
     * Vuelve a chequear las reglas con el registro bloqueado (lockForUpdate)
     * dentro de la transacción, por si el webhook de Mercado Pago confirma
     * el pago justo en el mismo instante en que se está cancelando.
     *
     * @throws \RuntimeException si el pedido ya no puede cancelarse.
     */
    public function cancelar(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!$this->puedeCancelarse($order)) {
                throw new \RuntimeException('Este pedido ya no puede cancelarse en su estado actual.');
            }

            $estabaPagado = $order->estado->value === 'pagado';

            if ($estabaPagado) {
                $this->reponerStock($order);
            }

            $order->estado = 'cancelado';
            $order->estado_envio = null;
            $order->cancelado_at = now();
            $order->save();

            return $order;
        });
    }

    private function reponerStock(Order $order): void
    {
        foreach ($order->items as $item) {
            $movimientos = OrderItemStockMovement::where('order_item_id', $item->id)->get();

            foreach ($movimientos as $movimiento) {
                if ($movimiento->producto_vencimiento_id) {
                    ProductoVencimiento::whereKey($movimiento->producto_vencimiento_id)
                        ->increment('cantidad', $movimiento->cantidad);
                }
            }
        }
    }
}
