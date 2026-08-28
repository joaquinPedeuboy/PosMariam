<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoEcommerce extends Model
{
    use HasFactory;
    
    protected $table = 'producto_ecommerce';

    protected $fillable = [
        'producto_id', 'slug', 'descripcion', 'activo', 'destacado',
        'precio_online', 'precio_oferta', 'stock_maximo_web',
        'meta_title', 'meta_description',
        'grupo_variante_id', 'variante_tamano', 'variante_color_nombre', 'variante_color_hex',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'destacado' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Última línea de defensa: sin importar qué código intente guardar
        // un ProductoEcommerce (el admin, un import futuro, un comando de
        // consola, etc.), esto bloquea un precio_oferta que no sea
        // realmente más barato que el precio de lista. La validación
        // "linda" con mensaje por campo vive en el controller — esto es
        // el respaldo que no se puede esquivar.
        static::saving(function (ProductoEcommerce $productoEcommerce) {
            if ($productoEcommerce->precio_oferta !== null
                && (float) $productoEcommerce->precio_oferta >= $productoEcommerce->precio_final) {
                throw new \InvalidArgumentException(
                    'El precio de oferta debe ser menor al precio de lista.'
                );
            }

            if ($productoEcommerce->grupo_variante_id !== null) {
                $duplicado = self::where('grupo_variante_id', $productoEcommerce->grupo_variante_id)
                    ->where('variante_tamano', $productoEcommerce->variante_tamano)
                    ->where('variante_color_nombre', $productoEcommerce->variante_color_nombre)
                    ->when(
                        $productoEcommerce->exists,
                        fn ($q) => $q->whereKeyNot($productoEcommerce->id)
                    )
                    ->exists();

                if ($duplicado) {
                    throw new \InvalidArgumentException(
                        'Ya existe otro producto en este grupo con la misma combinación de tamaño y color.'
                    );
                }
            }
        });
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaEcommerce::class,
            'categoria_producto_ecommerce',
            'producto_ecommerce_id',
            'categoria_ecommerce_id'
        );
    }

    // Precio de lista (sin descuento), el mismo cálculo de siempre.
    public function getPrecioFinalAttribute()
    {
        return $this->precio_online ?? $this->producto->precio;
    }

    /**
     * True solo si hay un precio de oferta cargado Y es efectivamente más
     * barato que el precio de lista. Evita mostrar una "oferta" que en
     * realidad no descuenta nada (por un precio_oferta mal cargado, o
     * un precio_online que bajó después y quedó por debajo de la oferta).
     */
    public function getEnOfertaAttribute(): bool
    {
        return $this->precio_oferta !== null && (float) $this->precio_oferta < $this->precio_final;
    }

    /**
     * El precio que realmente se cobra: el de oferta si aplica, si no el
     * de lista. Esto es lo que hay que usar en el checkout, NUNCA
     * precio_final directo, o el descuento que se muestra en la web sería
     * mentira (se vería tachado pero se cobraría el precio de lista igual).
     */
    public function getPrecioActualAttribute()
    {
        return $this->en_oferta ? (float) $this->precio_oferta : $this->precio_final;
    }

    /**
     * Porcentaje de descuento redondeado (ej: 20 para "-20%"), solo si
     * está en oferta. null si no, para que el frontend sepa fácil cuándo
     * no mostrar el cartelito de descuento.
     */
    public function getPorcentajeDescuentoAttribute(): ?int
    {
        if (!$this->en_oferta) {
            return null;
        }

        $descuento = 1 - ((float) $this->precio_oferta / $this->precio_final);
        
        return (int) round($descuento * 100);
    }

    // Stock que se muestra en la web, respetando el tope si existe
    public function getStockWebAttribute()
    {
        $stockReal = (int) $this->producto->stock_total;

        if ($this->stock_maximo_web === null) {
            return $stockReal;
        }

        return min($stockReal, (int) $this->stock_maximo_web);
    }


    /**
     * Arma la info de variantes (tamaños y colores del mismo grupo) para
     * mostrar el selector en el storefront. null si este producto no
     * pertenece a ningún grupo.
     *
     * Cada opción trae 'activo' (true si existe un producto real con esa
     * combinación) y 'slug' (a dónde navegar). Si 'activo' es false, el
     * frontend la muestra deshabilitada en vez de mandar a un producto
     * que no existe.
     */
    public function variantesDisponibles(): ?array
        {
            if ($this->grupo_variante_id === null) {
                return null;
            }
    
            $hermanos = self::where('grupo_variante_id', $this->grupo_variante_id)
                ->where('activo', true)
                ->get(['id', 'slug', 'variante_tamano', 'variante_color_nombre', 'variante_color_hex']);
    
            $tamanoActual = $this->variante_tamano;
            $colorActual = $this->variante_color_nombre;
    
            $tamanos = $hermanos->pluck('variante_tamano')->filter()->unique()->values();
            $colores = $hermanos->pluck('variante_color_nombre')->filter()->unique()->values();
    
            $listaTamanos = $tamanos->map(function ($tamano) use ($hermanos, $colorActual) {
                // El hermano que combina este tamaño con el color que estoy
                // viendo ahora (si este grupo también tiene eje de color).
                $match = $hermanos->first(fn ($h) => $h->variante_tamano === $tamano
                    && ($colorActual === null || $h->variante_color_nombre === $colorActual));
    
                return [
                    'valor' => $tamano,
                    'slug' => $match?->slug,
                    'disponible' => $match !== null,
                ];
            })->values()->toArray();
    
            $listaColores = $colores->map(function ($color) use ($hermanos, $tamanoActual) {
                $conEsteColor = $hermanos->where('variante_color_nombre', $color);
    
                $match = $conEsteColor->first(fn ($h) => $tamanoActual === null || $h->variante_tamano === $tamanoActual);
    
                return [
                    'valor' => $color,
                    // El hex se toma de cualquier hermano con ese color, aunque
                    // esta combinación puntual no exista — así el círculo se ve
                    // pintado con el color real aunque esté deshabilitado.
                    'hex' => $conEsteColor->first()?->variante_color_hex,
                    'slug' => $match?->slug,
                    'disponible' => $match !== null,
                ];
            })->values()->toArray();
    
            return [
                'tamanos' => $listaTamanos,
                'colores' => $listaColores,
            ];
        }
}
