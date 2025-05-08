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
    
    public function scopeDisponibles($query)
    {
        return $query->where('disponible', true);
    }

}
