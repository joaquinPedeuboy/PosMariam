<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoEcommerce extends Model
{
    use HasFactory;
    
    protected $table = 'producto_ecommerce';

    protected $fillable = [
        'producto_id', 'slug', 'descripcion', 'activo', 'destacado',
        'precio_online', 'precio_oferta', 'stock_maximo_web', 'stock_maximo_web_oferta',
        'meta_title', 'meta_description',
        'grupo_variante_id', 'variante_tamano', 'variante_color_nombre', 'variante_color_hex',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'destacado' => 'boolean',
        'stock_maximo_web' => 'integer',
        'stock_maximo_web_oferta' => 'integer',
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
    // Si no hay precio online configurado, usa el precio del POS.
    public function getPrecioFinalAttribute()
    {
        return $this->precio_online ?? $this->producto->precio;
    }

    /**
     * Determina si el precio promocional es válido.
     * No implica que haya unidades promocionales disponibles.
     */
    public function getTienePrecioOfertaValidoAttribute(): bool
    {
        return $this->precio_oferta !== null
            && (float) $this->precio_oferta < (float) $this->precio_final;
    }

    /**
     * Indica si hay unidades promocionales que se pueden vender online
     * y si el precio promocional es válido.
     */
    public function getEnOfertaAttribute(): bool
    {
        return $this->tiene_precio_oferta_valido
            && $this->stock_oferta_disponible > 0;
    }

    /**
     * Precio que corresponde a la fuente de stock disponible.
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
        if (!$this->en_oferta || (float) $this->precio_final <= 0) {
            return null;
        }

        $descuento = 1 - ((float) $this->precio_oferta / $this->precio_final);
        
        return (int) round($descuento * 100);
    }

    /**
     * Devuelve la consulta de reservas que siguen activas
     * y cuya fecha de expiración todavía no pasó.
     */
    private function reservasActivasQuery(): Builder
    {
        return OrderStockReservation::query()
            ->where(
                'estado',
                OrderStockReservation::ESTADO_ACTIVA
            )
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Cantidad de unidades de una oferta reservadas por pedidos.
     */
    private function cantidadReservadaOferta(int $ofertaId): int
    {
        return (int) $this->reservasActivasQuery()
            ->where('oferta_id', $ofertaId)
            ->sum('cantidad');
    }

    /**
     * Cantidad de unidades de stock normal reservadas por pedidos.
     */
    private function cantidadReservadaStockNormal(): int
    {
        $productoId = $this->producto_id;

        return (int) $this->reservasActivasQuery()
            ->whereHas(
                'productoVencimiento',
                function (Builder $query) use ($productoId) {
                    $query->where('producto_id', $productoId);
                }
            )
            ->sum('cantidad');
    }

    // Disponibilidad de ofertas

    /**
     * Unidades promocionales físicamente disponibles,
     * descontando las reservas activas.
     *
     * No aplica el límite web en este cálculo.
     */
    public function getStockOfertaFisicoDisponibleAttribute(): int
    {
        $oferta = $this->producto->ofertas;

        if (!$oferta) {
            return 0;
        }

        $reservado = $this->cantidadReservadaOferta(
            (int) $oferta->id
        );

        return max(
            0,
            (int) $oferta->cantidad - $reservado
        );
    }

    /**
     * Unidades promocionales vendibles online.
     *
     * Aplica el límite independiente de ofertas. Si es null,
     * no existe un límite adicional de stock web.
     */
    public function getStockOfertaDisponibleAttribute(): int
    {
        $stockFisicoDisponible = $this->stock_oferta_fisico_disponible;

        if ($this->stock_maximo_web_oferta === null) {
            return $stockFisicoDisponible;
        }

        $oferta = $this->producto->ofertas;

        if (!$oferta) {
            return 0;
        }

        $reservado = $this->cantidadReservadaOferta(
            (int) $oferta->id
        );

        return max(
            0,
            min(
                $stockFisicoDisponible,
                (int) $this->stock_maximo_web_oferta - $reservado
            )
        );
    }

    // Disponibilidad de stock normal

    /**
     * Stock normal físicamente disponible, antes de aplicar
     * el límite de unidades vendibles por internet.
     */
    public function getStockNormalFisicoDisponibleAttribute(): int
    {
        $stockReal = (int) $this->producto->stock_total;
        $reservado = $this->cantidadReservadaStockNormal();

        return max(0, $stockReal - $reservado);
    }

    /**
     * Stock normal vendible online.
     *
     * Respeta tanto el stock físico restante como el límite web.
     * Las reservas activas descuentan unidades de ambos límites.
     */
    public function getStockNormalDisponibleAttribute(): int
    {
        $stockFisicoDisponible = $this->stock_normal_fisico_disponible;

        if ($this->stock_maximo_web === null) {
            return $stockFisicoDisponible;
        }

        $reservado = $this->cantidadReservadaStockNormal();

        return max(
            0,
            min(
                $stockFisicoDisponible,
                (int) $this->stock_maximo_web - $reservado
            )
        );
    }

    /**
     * Stock vendible que se muestra en la tienda.
     *
     * Si hay oferta disponible, devuelve exclusivamente el stock
     * de oferta. Si no, devuelve el stock normal.
     */
    public function getStockWebAttribute(): int
    {
        if ($this->en_oferta) {
            return $this->stock_oferta_disponible;
        }

        return $this->stock_normal_disponible;
    }

    /**
     * La forma "de listado" del producto (card, favoritos, carruseles).
     * Centralizado acá para no repetir el mismo mapeo en cada controller
     * que necesita mostrar una lista de productos.
     */
    public function paraListado(): array
    {
        $imagenes = $this->producto->imagenes
            ->sortByDesc('principal')
            ->pluck('url')
            ->values();
            
        $enOferta = $this->en_oferta;
        $precioFinal = $this->precio_final;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nombre' => $this->producto->nombre,
            'precio' => $this->precio_final,
            'precio_oferta' => $enOferta ? $this->precio_oferta : null,
            'precio_actual' => $enOferta ? (float) $this->precio_oferta : $precioFinal,
            'en_oferta' => $enOferta,
            'porcentaje_descuento' => $enOferta
                ? $this->porcentaje_descuento
                : null,
            'stock' => $enOferta
                ? $this->stock_oferta_disponible
                : $this->stock_normal_disponible,
            'imagen' => $imagenes->first(),
            'imagenes' => $imagenes->toArray(),
            'categorias' => $this->categorias->pluck('nombre'),
        ];
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
