<?php

namespace App\Models;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Venta extends Model
{
    //
    use HasFactory;

    protected $fillable = ['total'];

    // Relación con productos
    public function productos()
    {
        return $this->belongsToMany(Producto::class, 'venta_productos')
                    ->withPivot([
                        'cantidad', 
                        'precio_unitario', 
                        'subtotal', 
                        'fecha_vencimiento', 
                        'fue_oferta', 
                        'precio_oferta'
                    ])
                    ->withTimestamps();
    }
}
