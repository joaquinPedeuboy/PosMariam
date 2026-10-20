<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Oferta;
use App\Models\Order;
use App\Models\OrderItemStockMovement;
use App\Models\OrderStockReservation;
use App\Models\ProductoVencimiento;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderCancellationService
{
    public function puedeCancelarse(Order $order): bool
    {
        $estadoCancelable = in_array(
            $order->estado->value,
            [
                OrderStatus::PendientePago->value,
                OrderStatus::Pagado->value,
            ],
            true
        );

        $envioYaEnCurso = $order->estado_envio
            && in_array(
                $order->estado_envio->value,
                ['enviado', 'entregado'],
                true
            );

        return $estadoCancelable && !$envioYaEnCurso;
    }

    public function cancelar(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $orden = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$this->puedeCancelarse($orden)) {
                throw new RuntimeException(
                    'Esta orden no se puede cancelar.'
                );
            }

            $estabaPagada =
                $orden->estado === OrderStatus::Pagado;

            $orden->load('items');

            if ($estabaPagada) {
                $this->reponerStock($orden);
            } else {
                $this->liberarReservas($orden);
            }

            $orden->estado = OrderStatus::Cancelado;
            $orden->estado_envio = null;
            $orden->cancelado_at = now();
            $orden->save();

            return $orden;
        });
    }

    /**
     * Las órdenes pendientes no han descontado stock físico.
     * Solo liberamos sus reservas activas.
     */
    private function liberarReservas(Order $orden): void
    {
        $reservas = OrderStockReservation::query()
            ->whereHas('orderItem', function ($query) use ($orden) {
                $query->where('order_id', $orden->id);
            })
            ->where(
                'estado',
                OrderStockReservation::ESTADO_ACTIVA
            )
            ->lockForUpdate()
            ->get();

        foreach ($reservas as $reserva) {
            $reserva->update([
                'estado' => OrderStockReservation::ESTADO_LIBERADA,
            ]);
        }
    }

    /**
     * Repone el stock que realmente se descontó al pagar.
     */
    private function reponerStock(Order $orden): void
    {
        foreach ($orden->items as $item) {
            // Reponer lotes normales usando los movimientos registrados.
            $movimientos = OrderItemStockMovement::query()
                ->where('order_item_id', $item->id)
                ->lockForUpdate()
                ->get();

            foreach ($movimientos as $movimiento) {
                $lote = ProductoVencimiento::query()
                    ->whereKey($movimiento->producto_vencimiento_id)
                    ->lockForUpdate()
                    ->first();

                if (!$lote) {
                    throw new RuntimeException(
                        "No se encontró el lote {$movimiento->producto_vencimiento_id} para reponer stock."
                    );
                }

                $lote->increment(
                    'cantidad',
                    $movimiento->cantidad
                );
            }

            // Reponer las unidades de oferta consumidas por este artículo.
            $reservasOferta = OrderStockReservation::query()
                ->where('order_item_id', $item->id)
                ->whereNotNull('oferta_id')
                ->where(
                    'estado',
                    OrderStockReservation::ESTADO_CONSUMIDA
                )
                ->lockForUpdate()
                ->get();

            foreach ($reservasOferta as $reserva) {
                $oferta = Oferta::query()
                    ->whereKey($reserva->oferta_id)
                    ->lockForUpdate()
                    ->first();

                if (!$oferta) {
                    throw new RuntimeException(
                        "No se encontró la oferta {$reserva->oferta_id} para reponer stock."
                    );
                }

                $oferta->increment(
                    'cantidad',
                    $reserva->cantidad
                );
            }
        }
    }
}