<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrupoVariante extends Model
{
    protected $fillable = ['nombre'];
    
    public function productos()
    {
        return $this->hasMany(ProductoEcommerce::class, 'grupo_variante_id');
    }
}
