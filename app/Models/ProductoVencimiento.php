<?php

namespace App\Models;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductoVencimiento extends Model
{
    use HasFactory;

    protected $fillable = ['producto_id', 'fecha_vencimiento', 'cantidad'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
