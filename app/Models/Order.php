<?php

namespace App\Models;

use App\Enums\OrderShippingStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'numero', 'customer_id', 'estado', 'subtotal', 'total',
        'nombre_contacto', 'telefono_contacto', 'email_contacto',
        'direccion_envio', 'referencia_direccion',
        'ciudad', 'provincia', 'codigo_postal',
        'mercado_pago_preference_id', 'mercado_pago_payment_id', 'pagado_at',
        'estado_envio', 'transportista', 'numero_seguimiento',
        'enviado_at', 'entregado_at', 'cancelado_at','metodo_entrega',
        'costo_envio', 'customer_address_id', 'tipo_domicilio', 'calle',
        'numero_calle', 'piso', 'departamento', 'torre_edificio', 'informacion_adicional',
    ];

    protected $casts = [
        'estado' => OrderStatus::class,
        'estado_envio' => OrderShippingStatus::class,
        'pagado_at' => 'datetime',
        'enviado_at' => 'datetime',
        'entregado_at' => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generarNumeroUnico(): string
    {
        do {
            $numero = 'ORD-' . strtoupper(Str::random(8));
        } while (self::where('numero', $numero)->exists());

        return $numero;
    }

    /**
     * Código de tracking propio, para usar cuando se envía por encomienda
     * o cualquier transporte que no da un número de seguimiento formal.
     */
    public static function generarNumeroSeguimientoInterno(): string
    {
        do {
            $numero = 'TRK-' . strtoupper(Str::random(8));
        } while (self::where('numero_seguimiento', $numero)->exists());
        
        return $numero;
    }

    public function customerAddress()
    {
        return $this->belongsTo(
            CustomerAddress::class,
            'customer_address_id'
        );
    }
}
