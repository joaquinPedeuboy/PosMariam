<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirarOrdenesPendientes extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description =
        'Cancela órdenes impagas vencidas y libera sus reservas de stock';

    public function handle(): int
    {
        $ahora = now();

        /*
         * Buscamos órdenes pendientes que tengan reservas vencidas
         * y ninguna reserva activa que todavía esté vigente.
         */
        $ordenesIds = Order::query()
            ->where('estado', OrderStatus::PendientePago->value)
            ->whereHas('items.reservasStock', function ($query) use ($ahora) {
                $query
                    ->where('estado', OrderStockReservation::ESTADO_ACTIVA)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', $ahora);
            })
            ->whereDoesntHave('items.reservasStock', function ($query) use ($ahora) {
                $query
                    ->where('estado', OrderStockReservation::ESTADO_ACTIVA)
                    ->where(function ($subquery) use ($ahora) {
                        $subquery
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $ahora);
                    });
            })
            ->pluck('id');

        $canceladas = 0;

        foreach ($ordenesIds as $ordenId) {
            try {
                $cancelada = DB::transaction(function () use ($ordenId) {
                    $orden = Order::query()
                        ->whereKey($ordenId)
                        ->lockForUpdate()
                        ->first();

                    if (
                        !$orden
                        || $orden->estado !== OrderStatus::PendientePago
                    ) {
                        return false;
                    }

                    $reservasActivas = OrderStockReservation::query()
                        ->whereHas('orderItem', function ($query) use ($ordenId) {
                            $query->where('order_id', $ordenId);
                        })
                        ->where(
                            'estado',
                            OrderStockReservation::ESTADO_ACTIVA
                        )
                        ->lockForUpdate()
                        ->get();

                    if ($reservasActivas->isEmpty()) {
                        return false;
                    }

                    $ahora = now();

                    // Si alguna reserva todavía está vigente,
                    // no cancelamos esta orden.
                    foreach ($reservasActivas as $reserva) {
                        if (
                            $reserva->expires_at === null
                            || $reserva->expires_at->isFuture()
                        ) {
                            return false;
                        }
                    }

                    foreach ($reservasActivas as $reserva) {
                        $reserva->update([
                            'estado' => OrderStockReservation::ESTADO_LIBERADA,
                        ]);
                    }

                    $orden->estado = OrderStatus::Cancelado;
                    $orden->cancelado_at = $ahora;
                    $orden->save();

                    return true;
                });

                if ($cancelada) {
                    $canceladas++;
                }
            } catch (\Throwable $e) {
                Log::error('Error al expirar una orden pendiente', [
                    'order_id' => $ordenId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Órdenes canceladas: {$canceladas}");

        return self::SUCCESS;
    }
}