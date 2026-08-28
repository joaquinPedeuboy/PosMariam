<?php

namespace App\Models;

use App\Models\Venta;
use App\Models\Imagen;
use App\Models\Oferta;
use App\Models\Departamento;
use App\Models\ProductoVencimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Producto extends Model
{
    use HasFactory;
    
    // Define los campos que pueden asignarse masivamente
    protected $fillable = ['nombre', 'precio', 'codigo_barras', 'departamento_id',];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d');
    }

    public function ventas()
    {
        return $this->belongsToMany(Venta::class, 'venta_productos')
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
    
    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true);
    }

    // Relacion con ecommerce
    public function ecommerce()
    {
        return $this->hasOne(ProductoEcommerce::class, 'producto_id');
    }

    public function imagenes()
    {
        return $this->hasMany(Imagen::class, 'producto_id')->orderBy('orden');
    }

    public function getStockTotalAttribute()
    {
        return $this->vencimientos()->sum('cantidad');
    }

}
