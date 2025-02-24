<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Imagen;
use App\Models\Oferta;
use App\Models\Producto;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductoVencimiento;
use App\Http\Requests\ProductoRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\ProductoCollection;
use App\Http\Requests\UpdateProductoRequest;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
         // Verifica si se solicita sin paginación
        if ($request->query('all')) {
            $productos = Producto::with('vencimientos')->get(); // Devuelve todos los productos sin paginación
            return response()->json(['data' => $productos]);
        }

        // Obtenemos los parámetros de búsqueda: nombre o código de barras
        $busqueda = $request->input('busqueda');
        
        // Creamos la consulta base para los productos
        $productosQuery = Producto::with('vencimientos'); 

        // Filtramos por nombre o código de barras si el parámetro existe
        if ($busqueda) {
            $productosQuery->where(function($query) use ($busqueda) {
                $query->where('nombre', 'like', '%' . $busqueda . '%')
                    ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
            });
        }


        // Permitimos que el frontend defina cuántos productos por página
        $perPage = $request->input('per_page', 5); // Valor por defecto: 5 productos por página

        // Realizamos la paginación de los productos filtrados
        $productos = $productosQuery->paginate($perPage);
    

        // Devolvemos la colección de productos con paginación
        return new ProductoCollection($productos);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductoRequest $request)
    {
        // Crear el producto
        $producto = Producto::create($request->validated());

        // Si el producto tiene un departamento, actualizar los precios de todos los productos de ese departamento
        if ($producto->departamento_id) {
            Producto::where('departamento_id', $producto->departamento_id)
                ->update(['precio' => $producto->precio]);  // Actualiza el precio de todos los productos del mismo departamento
        }

        foreach ($request->input('vencimientos') as $vencimiento) {
            ProductoVencimiento::create([
                'producto_id' => $producto->id,
                'fecha_vencimiento' => $vencimiento['fecha_vencimiento'],
                'cantidad' => $vencimiento['cantidad'],
            ]);
        }

         // Guardar ofertas
        // foreach ($request->input('ofertas', []) as $oferta) {
        //     Oferta::create([
        //         'producto_id' => $producto->id,
        //         'precio_oferta' => $oferta['precio_oferta'],
        //         'cantidad' => $oferta['cantidad'],
        //     ]);
        // }

        return response()->json([
            'producto' => $producto->load('vencimientos'),
            'message' => 'Producto creado con éxito',
            
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Producto $producto)
    {
        // Cargar los vencimientos relacionados con el producto
        return response()->json([
            'producto' => $producto->load('vencimientos'),
            'stock_total' => $producto->stock_total,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        // Actualizar datos del producto
        try {
             // Obtener los datos validados del request
            $validated = $request->validated();
            
            // Actualizar los detalles del producto
            $producto->update([
                'nombre' => $validated['nombre'] ?? $producto->nombre,
                'precio' => $validated['precio'] ?? $producto->precio,
                'codigo_barras' => $validated['codigo_barras'] ?? $producto->codigo_barras,
                'departamento_id' => $validated['departamento_id'] ?? null,
            ]);

            foreach ($request->input('vencimientos', []) as $vencimiento) {
                ProductoVencimiento::updateOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'fecha_vencimiento' => $vencimiento['fecha_vencimiento'],
                    ],
                    [
                        'cantidad' => $vencimiento['cantidad'],
                    ]
                );
            }

            // Actualizar ofertas
            // foreach ($request->input('ofertas', []) as $oferta) {
            //     Oferta::updateOrCreate(
            //         ['producto_id' => $producto->id, 'precio_oferta' => $oferta['precio_oferta']],
            //         ['cantidad' => $oferta['cantidad']]
            //     );
            // }


            // Si se actualiza el precio, modificamos todos los productos del mismo departamento
            if (isset($validated['precio']) && $producto->departamento_id) {
                Producto::where('departamento_id', $producto->departamento_id)
                    ->update(['precio' => $validated['precio']]);
            }            

            return response()->json([
                'message' => 'Producto y vencimientos actualizados correctamente',
                'producto' => $producto->load('vencimientos'),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto)
    {
        try {
            $producto->delete();
            return response()->json(['success' => true, 'message' => 'Producto eliminado con éxito']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Hubo un error al eliminar el producto'], 500);
        }
    }

    public function obtenerVencimientos(Producto $producto)
    {
        return response()->json($producto->vencimientos);
    }

    // public function verOferta(Producto $producto)
    // {
    //     // Verifica si el producto tiene una oferta asociada
    //     $oferta = $producto->ofertas()->first(); // Buscar la primera oferta activa

    //     if ($oferta) {
    //         return response()->json(['oferta' => $oferta], 200); // Si tiene una oferta, devolverla
    //     }

    //     return response()->json(['mensaje' => 'El producto no tiene una oferta disponible'], 404); // Si no tiene oferta
    // }

}
