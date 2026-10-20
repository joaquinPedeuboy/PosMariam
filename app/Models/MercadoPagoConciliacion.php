<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MercadoPagoConciliacion extends Model
{
    protected $table = 'mercado_pago_conciliaciones';

    protected $fillable = [
        'order_id',
        'payment_id',
        'external_reference',
        'payment_status',
        'motivo',
        'importe_recibido',
        'moneda_recibida',
        'detalles',
        'resuelto_at',
        'observaciones_resolucion',
    ];

    protected function casts(): array
    {
        return [
            'importe_recibido' => 'decimal:2',
            'detalles' => 'array',
            'resuelto_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function estaResuelta(): bool
    {
        return $this->resuelto_at !== null;
    }
}
