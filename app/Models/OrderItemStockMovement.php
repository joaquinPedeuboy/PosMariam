<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemStockMovement extends Model
{
    protected $fillable = ['order_item_id', 'producto_vencimiento_id', 'cantidad'];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
    
    public function productoVencimiento()
    {
        return $this->belongsTo(ProductoVencimiento::class);
    }
}
