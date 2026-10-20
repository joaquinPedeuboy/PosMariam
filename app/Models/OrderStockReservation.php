<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStockReservation extends Model
{
    public const ESTADO_ACTIVA = 'activa';
    public const ESTADO_CONSUMIDA = 'consumida';
    public const ESTADO_LIBERADA = 'liberada';

    protected $fillable = [
        'order_item_id',
        'producto_vencimiento_id',
        'oferta_id',
        'cantidad',
        'estado',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function productoVencimiento(): BelongsTo
    {
        return $this->belongsTo(ProductoVencimiento::class);
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(Oferta::class);
    }

    public function estaActiva(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA
            && (
                $this->expires_at === null
                || $this->expires_at->isFuture()
            );
    }
}
