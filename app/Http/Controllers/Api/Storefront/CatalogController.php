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

        if ($categoriaSlug = $request->input('categoria')) {
            $query->whereHas('categorias', function ($q) use ($categoriaSlug) {
                $q->where('slug', $categoriaSlug);
            });
        }

        if ($busqueda = $request->input('busqueda')) {
            $query->whereHas('producto', function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%");
            });
        }

        if ($request->boolean('destacados')) {
            $query->where('destacado', true);
        }

        if ($request->boolean('en_oferta')) {
            // El precio "de lista" real cae a producto.precio si no hay
            // precio_online cargado — el filtro tiene que respetar ese
            // mismo fallback, o si no quedarían afuera ofertas válidas.
            $query->join('productos', 'productos.id', '=', 'producto_ecommerce.producto_id')
                ->whereNotNull('producto_ecommerce.precio_oferta')
                ->whereRaw('producto_ecommerce.precio_oferta < COALESCE(producto_ecommerce.precio_online, productos.precio)')
                ->select('producto_ecommerce.*');
        }

        if ($idsParam = $request->input('ids')) {
            // Usado por la página de favoritos de invitados: el navegador
            // guarda los ids en localStorage, y esto los resuelve a
            // productos completos sin necesitar autenticación.
            $ids = array_filter(array_map('intval', explode(',', $idsParam)));
            $query->whereIn('producto_ecommerce.id', $ids);
        }

        $productos = $query->paginate($request->input('per_page', 20))
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

        $menuPrincipal = $categorias
            ->where('categoria_padre_id', null)
            ->where('mostrar_en_menu_principal', true)
            ->sortBy('orden')
            ->take(8)
            ->map(fn (CategoriaEcommerce $categoria) => [
                'nombre' => $categoria->nombre,
                'slug' => $categoria->slug,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'menu_principal' => $menuPrincipal,
            'hamburguesa' => $arbol,
        ]);
    }
}
