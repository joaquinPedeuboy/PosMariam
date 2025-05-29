<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\Imagen;
use App\Models\Oferta;
use App\Models\Producto;
use App\Models\ProductoPos;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $productos = Producto::with('vencimientos', 'ofertas')->get();
            return response()->json(['data' => $productos]);
        }

        if ($request->query('oferta')) {
            $productos = Producto::with('ofertas')->whereHas('ofertas')->get();
            return response()->json(['data' => $productos]);
        }

        if ($request->query('vencimientos')) {
            $productos = Producto::with('vencimientos')->whereHas('vencimientos')->get();
            return response()->json(['data' => $productos]);
        }

        // Obtenemos los parámetros de búsqueda: nombre o código de barras
        $busqueda = $request->input('busqueda');
        
        // Creamos la consulta base para los productos
        $productosQuery = Producto::with('vencimientos', 'ofertas'); 

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
        DB::beginTransaction();
        try {
            // Crear el producto
            $producto = Producto::create($request->validated());

            // Si el producto tiene un departamento, actualizar los precios de todos los productos de ese departamento
            if ($producto->departamento_id) {
                Producto::where('departamento_id', $producto->departamento_id)
                    ->update(['precio' => $producto->precio]);  // Actualiza el precio de todos los productos del mismo departamento
            }

            foreach ($request->input('vencimientos', []) as $vencimiento) {
                ProductoVencimiento::updateOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'fecha_vencimiento' => $vencimiento['fecha_vencimiento'],
                    ],
                    ['cantidad' => $vencimiento['cantidad']]
                );
            }

            if ($request->filled('oferta.precioOferta') && $request->filled('oferta.cantidadOferta')) {
                Oferta::updateOrCreate(
                    ['producto_id' => $producto->id],
                    [
                        'precio_oferta' => $request->input('oferta.precioOferta'),
                        'cantidad' => $request->input('oferta.cantidadOferta')
                    ]
                );
            } else {
                // Si no hay valores válidos, eliminar la oferta si existe
                Oferta::where('producto_id', $producto->id)->delete();
            }   

            DB::commit();
            return response()->json([
                'producto' => $producto->loadMissing('vencimientos', 'ofertas'),
                'message' => 'Producto creado con éxito',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    /**
     * Display the specified resource.
     */
    public function show(Producto $producto)
    {
        // Cargar los vencimientos relacionados con el producto
        return response()->json([
            'producto' => Producto::with('vencimientos', 'ofertas')->findOrFail($producto->id),
            'stock_total' => $producto->stock_total,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductoRequest $request, Producto $producto)
    {
        DB::beginTransaction();
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
                    ['cantidad' => $vencimiento['cantidad']]
                );
            }

            // Eliminar vencimientos no incluidos en la actualización
            $producto->vencimientos()
            ->whereNotIn('fecha_vencimiento', collect($request->input('vencimientos', []))->pluck('fecha_vencimiento'))
            ->delete();

            // Actualizar ofertas
            if ($request->filled('oferta.precioOferta') && $request->filled('oferta.cantidadOferta')) {
                Oferta::updateOrCreate(
                    ['producto_id' => $producto->id],
                    [
                        'precio_oferta' => $request->input('oferta.precioOferta'),
                        'cantidad' => $request->input('oferta.cantidadOferta')
                    ]
                );
            } else {
                // Si no hay valores válidos, eliminar la oferta si existe
                Oferta::where('producto_id', $producto->id)->delete();
            }         


            // Si se actualiza el precio, modificamos todos los productos del mismo departamento
            if (isset($validated['precio']) && $producto->departamento_id) {
                Producto::where('departamento_id', $producto->departamento_id)
                    ->update(['precio' => $validated['precio']]);
            }            

            DB::commit();
            return response()->json([
                'message' => 'Producto y vencimientos actualizados correctamente',
                'producto' => $producto->loadMissing('vencimientos', 'ofertas'),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
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

    public function verOferta(Producto $producto)
    {
        return response()->json([
            'ofertas' => $producto->ofertas ?? null
        ], 200);
    }

    public function toggleDisponibilidad(Producto $producto)
    {
        $producto->disponible = !$producto->disponible;
        $producto->save();

        return response()->json([
            'success'    => true,
            'disponible' => $producto->disponible,
        ]);
    }

    public function indexPublic(Request $request)
    {
        $busqueda = $request->input('busqueda');

        $productosQuery = Producto::disponibles()->with('vencimientos', 'ofertas');
        
        if ($busqueda) {
            $productosQuery->where(function($query) use ($busqueda) {
                $query->where('nombre', 'like', '%' . $busqueda . '%')
                    ->orWhere('codigo_barras', 'like', '%' . $busqueda . '%');
            });
        }

        $perPage = $request->input('per_page', 30);
        $productos = $productosQuery->paginate($perPage);

        return new ProductoCollection($productos);
    }

    // Busqueda por codigo de barras
    public function buscarPorCodigo($codigo)
    {
        $producto = Producto::with('vencimientos', 'ofertas')->where('codigo_barras', $codigo)->first();

        if (!$producto) {
            return response()->json(['mensaje' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto);
    }

    public function indexPos(Request $req)
    {
        $q     = $req->input('busqueda');
        $query = ProductoPos::select([
            'id','nombre','codigo_barras',
            'precio','stock_total','precio_oferta','stock_oferta'
        ]);

        if ($q) {
            $query->where('nombre', 'LIKE', "%{$q}%")
                ->orWhere('codigo_barras', $q);
        }

        // Limita a 50 resultados para autocompletar
        $productos = $query->orderBy('nombre')->limit(50)->get();

        return response()->json($productos);
    }


}
