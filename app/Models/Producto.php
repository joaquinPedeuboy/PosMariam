<?php

namespace App\Models;

use App\Models\Venta;
use App\Models\Imagen;
use App\Models\Oferta;
use App\Models\Departamento;
use App\Models\ProductoVencimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory;
    
    // Define los campos que pueden asignarse masivamente
    protected $fillable = ['nombre', 'precio', 'codigo_barras', 'departamento_id', 'disponible'];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d');
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'venta_productos')->withPivot(['cantidad', 'vencimiento_id', 'precio_unitario'])->withTimestamps();
    }

    public function departamento()
    {
        return $this->belongsTo(Departamento::class);
    }

    public function vencimientos()
    {
        return $this->hasMany(ProductoVencimiento::class);
    }

    public function ofertas() {
        return $this->hasOne(Oferta::class);
    }

    // Funcion que suma el total del stock de un producto
    public function stockTotal(): Attribute
    {
        return Attribute::get(function () {
            $vencimientos = $this->vencimientos->sum('cantidad');
            $oferta = $this->oferta?->cantidad ?? 0;

            return $vencimientos + $oferta;
        });
    }

    // Relacion que trae el vencimiento mas cercano del producto
    public function getVencimientoProximoAttribute()
    {
        return $this->vencimientos->sortBy('fecha_vencimiento')->first();
    }
    
}
