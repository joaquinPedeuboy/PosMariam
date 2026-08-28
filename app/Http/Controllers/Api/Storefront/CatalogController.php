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

        $productos = $query->paginate($request->input('per_page', 20))
            ->through(fn (ProductoEcommerce $pe) => [
                'id' => $pe->id,
                'slug' => $pe->slug,
                'nombre' => $pe->producto->nombre,
                'precio' => $pe->precio_final,
                'precio_oferta' => $pe->en_oferta ? $pe->precio_oferta : null,
                'precio_actual' => $pe->precio_actual,
                'en_oferta' => $pe->en_oferta,
                'porcentaje_descuento' => $pe->porcentaje_descuento,
                'stock' => $pe->stock_web,
                'imagen' => optional(
                    $pe->producto->imagenes->firstWhere('principal', true)
                        ?? $pe->producto->imagenes->first()
                )->url,
                'categorias' => $pe->categorias->pluck('nombre'),
            ]);

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
        ]);
    }

    public function categorias()
    {
        // Traemos TODAS las categorías en una sola consulta (la tabla es
        // chica) y armamos el árbol completo en memoria, sin importar
        // cuántos niveles de profundidad tenga cada rama.
        $categorias = CategoriaEcommerce::orderBy('orden')->get();

        return response()->json(CategoriaEcommerce::arbolPublico($categorias));
    }
}
