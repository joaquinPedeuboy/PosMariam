<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id',
        'nombre',
        'tipo_domicilio',
        'calle',
        'numero',
        'piso',
        'departamento',
        'torre_edificio',
        'informacion_adicional',
        'referencia_envio',
        'ciudad',
        'provincia',
        'codigo_postal',
        'nombre_contacto',
        'telefono_contacto',
        'es_predeterminada',
    ];

    protected function casts(): array
    {
        return [
            'es_predeterminada' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
