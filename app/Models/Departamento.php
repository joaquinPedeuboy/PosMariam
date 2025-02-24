<?php

namespace App\Models;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Departamento extends Model
{
    use HasFactory;

    protected $fillable = ['nombre'];

    // Relación uno a muchos con productos
    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
