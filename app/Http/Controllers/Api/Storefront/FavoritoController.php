<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use App\Models\ProductoEcommerce;
use Illuminate\Http\Request;

class FavoritoController extends Controller
{
    public function index(Request $request)
    {
        $ids = Favorito::where('customer_id', $request->user()->id)
            ->pluck('producto_ecommerce_id');

        $productos = ProductoEcommerce::whereIn('id', $ids)
            ->where('activo', true)
            ->with(['producto.imagenes', 'categorias'])
            ->get()
            ->map(fn (ProductoEcommerce $pe) => $pe->paraListado());

        return response()->json($productos);
    }

    public function store(Request $request, ProductoEcommerce $productoEcommerce)
    {
        Favorito::firstOrCreate([
            'customer_id' => $request->user()->id,
            'producto_ecommerce_id' => $productoEcommerce->id,
        ]);

        return response()->json(['message' => 'Agregado a favoritos'], 201);
    }

    public function destroy(Request $request, ProductoEcommerce $productoEcommerce)
    {
        Favorito::where('customer_id', $request->user()->id)
            ->where('producto_ecommerce_id', $productoEcommerce->id)
            ->delete();

        return response()->json(['message' => 'Quitado de favoritos']);
    }
}