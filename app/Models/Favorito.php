<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    protected $fillable = ['customer_id', 'producto_ecommerce_id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function productoEcommerce()
    {
        return $this->belongsTo(ProductoEcommerce::class);
    }
}
