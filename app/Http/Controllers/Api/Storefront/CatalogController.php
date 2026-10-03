<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CategoriaEcommerce;
use App\Models\ProductoEcommerce;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductoEcommerce::where('activo', true)
            ->with(['producto.imagenes', 'categorias']);

        $orden = $request->input('orden', 'relevancia');

        $necesitaPrecio =
            $request->boolean('en_oferta') ||
            is_numeric($request->input('precio_min')) ||
            is_numeric($request->input('precio_max')) ||
            in_array($orden, ['precio_asc', 'precio_desc']);

        if ($necesitaPrecio || $orden === 'nombre_asc') {
            $query->join(
                'productos',
                'productos.id',
                '=',
                'producto_ecommerce.producto_id'
            )
            ->select('producto_ecommerce.*');
        }

        /*
        |--------------------------------------------------------------------------
        | Precio de lista y precio actual
        |--------------------------------------------------------------------------
        |
        | Precio de lista:
        | - precio_online si existe
        | - productos.precio como fallback
        |
        | Precio actual:
        | - precio_oferta si realmente es menor al precio de lista
        | - precio de lista en caso contrario
        |--------------------------------------------------------------------------
        */
        $precioLista = '
            COALESCE(
                producto_ecommerce.precio_online,
                productos.precio
            )
        ';

        $precioActual = "
            CASE
                WHEN producto_ecommerce.precio_oferta IS NOT NULL
                    AND producto_ecommerce.precio_oferta < {$precioLista}
                THEN producto_ecommerce.precio_oferta

                ELSE {$precioLista}
            END
        ";

        /*
        |--------------------------------------------------------------------------
        | Categoría
        |--------------------------------------------------------------------------
        |
        | Al seleccionar una categoría padre también incluimos:
        | hijas, nietas, bisnietas, etc.
        |--------------------------------------------------------------------------
        */

        if ($categoriaSlug = $request->input('categoria')) {
            $categoria = CategoriaEcommerce::where('slug', $categoriaSlug)
            ->first();

            if ($categoria) {
                $categoriaIds = [
                    $categoria->id,
                    ...$categoria->todosLosDescendientesIds(),
                ];

                $query->whereHas('categorias', function ($q) use ($categoriaIds) {
                    $q->whereIn('categorias_ecommerce.id', $categoriaIds);
                });
            }
        }

        if ($busqueda = trim($request->input('busqueda', ''))) {
            $query->whereHas('producto', function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%");
            });
        }

        if ($request->boolean('destacados')) {
                $query->where(
                'producto_ecommerce.destacado',
                true
            );
        }

        /*
        |--------------------------------------------------------------------------
        | En oferta
        |--------------------------------------------------------------------------
        | precio_oferta debe existir y ser menor al precio de lista real.
        |
        | El precio de lista real es:
        | precio_online, si existe
        | o productos.precio como fallback.
        |--------------------------------------------------------------------------
        */
        if ($request->boolean('en_oferta')) {
            $query->whereNotNull(
                'producto_ecommerce.precio_oferta'
            )
            ->whereRaw(
                "producto_ecommerce.precio_oferta < {$precioLista}"
            );
        }
        /*
        |--------------------------------------------------------------------------
        | Precio
        |--------------------------------------------------------------------------
        */
        if (is_numeric($request->input('precio_min'))) {
            $query->whereRaw(
                "{$precioActual} >= ?",
                [(float) $request->input('precio_min')]
            );
        }

        if (is_numeric($request->input('precio_max'))) {
            $query->whereRaw(
                "{$precioActual} <= ?",
                [(float) $request->input('precio_max')]
            );
        }

        if ($idsParam = $request->input('ids')) {
            // Usado por la página de favoritos de invitados: el navegador
            // guarda los ids en localStorage, y esto los resuelve a
            // productos completos sin necesitar autenticación.
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
            $query->whereIn('producto_ecommerce.id', $ids);
        }

        /*
        |--------------------------------------------------------------------------
        | Ordenamiento
        |--------------------------------------------------------------------------
        */
        $orden = $request->input('orden', 'relevancia');

        switch ($orden) {

            case 'precio_asc':

                $query->orderByRaw(
                    "{$precioActual} ASC"
                );

                break;

            case 'precio_desc':

                $query->orderByRaw(
                    "{$precioActual} DESC"
                );

                break;

            case 'nombre_asc':

                $query->orderBy(
                    'productos.nombre',
                    'asc'
                );

                break;

            case 'nuevos':

                $query->orderByDesc(
                    'producto_ecommerce.created_at'
                );

                break;

            case 'relevancia':
            default:

                $query->orderByDesc(
                    'producto_ecommerce.destacado'
                )
                ->orderByDesc(
                    'producto_ecommerce.created_at'
                );

                break;
        }

        /*
        |--------------------------------------------------------------------------
        | Paginación
        |--------------------------------------------------------------------------
        */
        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );
        $productos = $query->paginate($perPage)
            ->through(fn (ProductoEcommerce $pe) => $pe->paraListado());

        return response()->json($productos);
    }

    public function show(string $slug)
    {
        $productoEcommerce = ProductoEcommerce::where('slug', $slug)
            ->where('activo', true)
            ->with(['producto.imagenes', 'categorias'])
            ->firstOrFail();

        return response()->json([
            'id' => $productoEcommerce->id,
            'slug' => $productoEcommerce->slug,
            'nombre' => $productoEcommerce->producto->nombre,
            'descripcion' => $productoEcommerce->descripcion,
            'precio' => $productoEcommerce->precio_final,
            'precio_oferta' => $productoEcommerce->en_oferta ? $productoEcommerce->precio_oferta : null,
            'precio_actual' => $productoEcommerce->precio_actual,
            'en_oferta' => $productoEcommerce->en_oferta,
            'porcentaje_descuento' => $productoEcommerce->porcentaje_descuento,
            'stock' => $productoEcommerce->stock_web,
            'meta_title' => $productoEcommerce->meta_title,
            'meta_description' => $productoEcommerce->meta_description,
            'imagenes' => $productoEcommerce->producto->imagenes->map(fn ($img) => [
                'url' => $img->url,
                'principal' => $img->principal,
            ]),
            'categorias' => $productoEcommerce->categorias->map(fn ($c) => [
                'nombre' => $c->nombre,
                'slug' => $c->slug,
            ]),
            'variante_tamano' => $productoEcommerce->variante_tamano,
            'variante_color_nombre' => $productoEcommerce->variante_color_nombre,
            'variantes' => $productoEcommerce->variantesDisponibles(),
            'relacionados' => $this->productosRelacionados($productoEcommerce),
        ]);
    }

    /**
     * Productos que comparten al menos una categoría con el actual.
     * Excluye al propio producto y a sus hermanos de variante (esos ya se
     * ven en el selector de tamaño/color, no tiene sentido repetirlos acá).
     */
    private function productosRelacionados(ProductoEcommerce $productoEcommerce, int $limite = 8): array
    {
        $categoriaIds = $productoEcommerce->categorias->pluck('id');

        if ($categoriaIds->isEmpty()) {
            return [];
        }

        return ProductoEcommerce::where('activo', true)
            ->where('id', '!=', $productoEcommerce->id)
            ->whereHas('categorias', fn ($q) => $q->whereIn('categorias_ecommerce.id', $categoriaIds))
            ->when(
                $productoEcommerce->grupo_variante_id !== null,
                fn ($q) => $q->where(function ($q2) use ($productoEcommerce) {
                    $q2->whereNull('grupo_variante_id')
                        ->orWhere('grupo_variante_id', '!=', $productoEcommerce->grupo_variante_id);
                })
            )
            ->with(['producto.imagenes', 'categorias'])
            ->inRandomOrder()
            ->limit($limite)
            ->get()
            ->map(fn (ProductoEcommerce $pe) => $pe->paraListado())
            ->values()
            ->toArray();
    }

    public function categorias()
    {
        $categorias = CategoriaEcommerce::orderBy('orden')->get();

        $arbol = CategoriaEcommerce::arbolPublico($categorias);

        $idsMenuPrincipal = $categorias
            ->whereNull('categoria_padre_id')
            ->where('mostrar_en_menu_principal', true)
            ->sortBy('orden')
            ->take(8)
            ->pluck('id')
            ->toArray();

        $menuPrincipal = collect($arbol)
            ->filter(function ($categoria) use ($categorias, $idsMenuPrincipal) {
                return $categorias
                    ->whereIn('id', $idsMenuPrincipal)
                    ->contains('slug', $categoria['slug']);
            })
            ->values()
            ->toArray();

        return response()->json([
            'menu_principal' => $menuPrincipal,
            'hamburguesa' => $arbol,
        ]);
    }
}
