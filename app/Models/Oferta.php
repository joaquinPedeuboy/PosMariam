<?php

namespace App\Models;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Oferta extends Model
{
    use HasFactory;

    protected $fillable = ['producto_id', 'precio_oferta', 'cantidad'];

    public function producto() {
        return $this->belongsTo(Producto::class);
    }

    // public function isAvailable($cantidad)
    // {
    //     return $this->cantidad >= $cantidad; // Verifica si hay suficiente cantidad en oferta
    // }
}
