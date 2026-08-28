<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'producto_id', 'producto_ecommerce_id', 'nombre_producto',
        'precio_unitario', 'cantidad', 'faltante', 'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function productoEcommerce()
    {
        return $this->belongsTo(ProductoEcommerce::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
