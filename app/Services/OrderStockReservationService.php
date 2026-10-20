<?php

namespace App\Services;

use App\Models\Oferta;
use App\Models\OrderItem;
use App\Models\OrderStockReservation;
use App\Models\ProductoEcommerce;
use App\Models\ProductoVencimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStockReservationService
{
    /**
     * Selecciona y bloquea la fuente de stock para una compra.
     *
     * Debe ejecutarse dentro de una transacción de base de datos.
     *
     * Devuelve el precio unitario y el detalle de las unidades
     * que posteriormente se reservarán.
     */
    public function prepararReserva(
        ProductoEcommerce $productoEcommerce,
        int $cantidad
    ): array {
        $producto = $productoEcommerce->producto;

        if (!$producto) {
            throw ValidationException::withMessages([
                'items' => 'El producto ya no está disponible.',
            ]);
        }

        // Bloqueamos primero la oferta para evitar que otra transacción
        // modifique sus unidades mientras comprobamos la disponibilidad.
        $oferta = Oferta::where('producto_id', $producto->id)
            ->lockForUpdate()
            ->first();

        $precioLista = (float) $productoEcommerce->precio_final;

        $tienePrecioOfertaValido = $oferta !== null
            && $productoEcommerce->precio_oferta !== null
            && (float) $productoEcommerce->precio_oferta < $precioLista;

        if ($tienePrecioOfertaValido) {
            $reservadoOferta = $this->reservasActivasQuery()
                ->where('oferta_id', $oferta->id)
                ->sum('cantidad');

            $stockFisicoDisponible = max(
                0,
                (int) $oferta->cantidad - (int) $reservadoOferta
            );

            $stockWebDisponible = $productoEcommerce->stock_maximo_web_oferta === null
                ? $stockFisicoDisponible
                : max(
                    0,
                    min(
                        $stockFisicoDisponible,
                        (int) $productoEcommerce->stock_maximo_web_oferta
                            - (int) $reservadoOferta
                    )
                );

            // Si queda stock promocional vendible, no mezclamos
            // unidades normales para completar la compra.
            if ($stockWebDisponible > 0) {
                if ($cantidad > $stockWebDisponible) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'No hay suficientes unidades de oferta de "%s". Disponibles: %d.',
                            $producto->nombre,
                            $stockWebDisponible
                        ),
                    ]);
                }

                return [
                    'fuente' => 'oferta',
                    'precio_unitario' => (float) $productoEcommerce->precio_oferta,
                    'reservas' => [[
                        'oferta_id' => $oferta->id,
                        'producto_vencimiento_id' => null,
                        'cantidad' => $cantidad,
                    ]],
                ];
            }
        }

        // Si no quedan unidades promocionales vendibles, utilizamos
        // exclusivamente stock normal.
        $lotes = ProductoVencimiento::where('producto_id', $producto->id)
            ->where('cantidad', '>', 0)
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($lotes->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'No hay stock disponible de "%s".',
                    $producto->nombre
                ),
            ]);
        }

        $idsLotes = $lotes->pluck('id');

        $reservasPorLote = $this->reservasActivasQuery()
            ->whereIn('producto_vencimiento_id', $idsLotes)
            ->selectRaw(
                'producto_vencimiento_id, SUM(cantidad) AS cantidad_reservada'
            )
            ->groupBy('producto_vencimiento_id')
            ->pluck('cantidad_reservada', 'producto_vencimiento_id');

        $stockFisico = (int) $lotes->sum('cantidad');

        $reservadoNormal = (int) $reservasPorLote->sum();

        $stockFisicoDisponible = max(
            0,
            $stockFisico - $reservadoNormal
        );

        $limiteWeb = $productoEcommerce->stock_maximo_web === null
            ? $stockFisico
            : min(
                $stockFisico,
                (int) $productoEcommerce->stock_maximo_web
            );

        $stockWebDisponible = max(
            0,
            min(
                $stockFisicoDisponible,
                $limiteWeb - $reservadoNormal
            )
        );

        if ($cantidad > $stockWebDisponible) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'No hay stock suficiente de "%s". Disponibles: %d.',
                    $producto->nombre,
                    $stockWebDisponible
                ),
            ]);
        }

        // Reservamos primero los lotes con vencimiento más cercano (FEFO).
        $restante = $cantidad;
        $reservas = [];

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $reservadoEnLote = (int) (
                $reservasPorLote[$lote->id] ?? 0
            );

            $disponibleEnLote = max(
                0,
                (int) $lote->cantidad - $reservadoEnLote
            );

            if ($disponibleEnLote === 0) {
                continue;
            }

            $cantidadAReservar = min(
                $restante,
                $disponibleEnLote
            );

            $reservas[] = [
                'oferta_id' => null,
                'producto_vencimiento_id' => $lote->id,
                'cantidad' => $cantidadAReservar,
            ];

            $restante -= $cantidadAReservar;
        }

        // Defensa adicional ante cualquier inconsistencia de stock.
        if ($restante > 0) {
            throw ValidationException::withMessages([
                'items' => sprintf(
                    'No fue posible reservar todo el stock de "%s".',
                    $producto->nombre
                ),
            ]);
        }

        return [
            'fuente' => 'normal',
            'precio_unitario' => $precioLista,
            'reservas' => $reservas,
        ];
    }

    /**
     * Guarda las reservas vinculadas al artículo del pedido.
     *
     * Debe ejecutarse en la misma transacción en la que se prepara
     * la reserva y se crea el pedido.
     */
    public function crearReservas(
        OrderItem $item,
        array $plan,
        Carbon $expiresAt
    ): void {
        foreach ($plan['reservas'] as $reserva) {
            OrderStockReservation::create([
                'order_item_id' => $item->id,
                'producto_vencimiento_id' =>
                    $reserva['producto_vencimiento_id'],
                'oferta_id' => $reserva['oferta_id'],
                'cantidad' => $reserva['cantidad'],
                'estado' => OrderStockReservation::ESTADO_ACTIVA,
                'expires_at' => $expiresAt,
            ]);
        }
    }

    /**
     * Consulta reutilizable para contar reservas que siguen activas
     * y todavía no vencieron.
     */
    private function reservasActivasQuery(): Builder
    {
        return OrderStockReservation::query()
            ->where(
                'estado',
                OrderStockReservation::ESTADO_ACTIVA
            )
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
