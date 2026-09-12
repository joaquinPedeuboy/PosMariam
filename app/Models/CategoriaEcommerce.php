<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CategoriaEcommerce extends Model
{
    use HasFactory;
    
    protected $table = 'categorias_ecommerce';
    protected $fillable = ['nombre', 'slug', 'categoria_padre_id', 'orden', 'mostrar_en_menu_principal'];

    protected $casts = [
        'mostrar_en_menu_principal' => 'boolean',
    ];

    const MAX_PROFUNDIDAD = 4;

    public function productos()
    {
        return $this->belongsToMany(
            ProductoEcommerce::class,
            'categoria_producto_ecommerce',
            'categoria_ecommerce_id',
            'producto_ecommerce_id'
        );
    }

    public function subcategorias()
    {
        return $this->hasMany(CategoriaEcommerce::class, 'categoria_padre_id');
    }

    public function categoriaPadre()
    {
        return $this->belongsTo(CategoriaEcommerce::class, 'categoria_padre_id');
    }

    public function profundidad(): int
    {
        $nivel = 0;
        $actual = $this;

        while ($actual->categoria_padre_id !== null) {
            $actual = $actual->categoriaPadre;
            $nivel++;

            // Salvavidas por si alguna vez se cuela un ciclo en la BD.
            if ($nivel > 20) {
                break;
            }
        }

        return $nivel;
    }

    /**
     * IDs de TODAS las subcategorías descendientes (hijas, nietas, etc).
     * Se usa para evitar referencias circulares y para chequear antes de borrar.
     */
    public function todosLosDescendientesIds(): array
    {
        $ids = [];

        foreach ($this->subcategorias as $hija) {
            $ids[] = $hija->id;
            $ids = array_merge($ids, $hija->todosLosDescendientesIds());
        }

        return $ids;
    }

    /**
     * Ruta completa tipo "Perfumes > Hombre > Nicho", para breadcrumbs
     * y para mostrar en los selects de "categoría padre" del admin.
     */
    public function rutaCompleta(string $separador = ' > '): string
    {
        $partes = [$this->nombre];
        $actual = $this;

        while ($actual->categoriaPadre) {
            $actual = $actual->categoriaPadre;
            array_unshift($partes, $actual->nombre);
        }

        return implode($separador, $partes);
    }

    /**
     * Arma un árbol anidado a partir de una colección plana de categorías.
     * Pensada para usarse junto con ->withCount('productos').
     */
    public static function construirArbol(Collection $categorias, ?int $padreId = null): array
    {
        return $categorias
            ->where('categoria_padre_id', $padreId)
            ->sortBy('orden')
            ->values()
            ->map(function (CategoriaEcommerce $cat) use ($categorias) {
                return [
                    'id' => $cat->id,
                    'nombre' => $cat->nombre,
                    'slug' => $cat->slug,
                    'orden' => $cat->orden,
                    'mostrar_en_menu_principal' => $cat->mostrar_en_menu_principal,
                    'cantidad_productos' => $cat->productos_count ?? 0,
                    'hijos' => self::construirArbol($categorias, $cat->id),
                ];
            })
            ->toArray();
    }

    /**
     * Igual que construirArbol(), pero para el storefront público: solo
     * expone nombre/slug/subcategorias (sin id, orden ni cantidad de
     * productos, que son datos internos del admin). Recorre TODOS los
     * niveles, a diferencia de un with('subcategorias') que solo trae un
     * nivel de profundidad.
     */
    public static function arbolPublico(Collection $categorias, ?int $padreId = null): array
    {
        return $categorias
            ->where('categoria_padre_id', $padreId)
            ->sortBy('orden')
            ->values()
            ->map(fn (CategoriaEcommerce $cat) => [
                'nombre' => $cat->nombre,
                'slug' => $cat->slug,
                'subcategorias' => self::arbolPublico($categorias, $cat->id),
            ])
            ->toArray();
    }
}
