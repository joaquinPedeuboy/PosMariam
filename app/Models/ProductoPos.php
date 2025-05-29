<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// App/Models/ProductoPos.php
class ProductoPos extends Model
{
    protected $table = 'vw_productos_pos';
    public    $timestamps = false;
    protected $fillable = [
        'id','nombre','codigo_barras','precio',
        'stock_total','precio_oferta','stock_oferta'
    ];
}

