<?php

namespace App\Http\Controllers;

use App\Models\Oferta;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ProductoVencimiento;
use App\Http\Requests\ProductoRequest;
use App\Http\Resources\ProductoCollection;
use App\Http\Requests\UpdateProductoRequest;
use App\Http\Resources\ProductoConsultaResource;

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
            $perPage = $request->input('per_page', 30);
            $query = Producto::with('ofertas');
            $query->whereHas('ofertas');
            // Paginación
            $productos = $query->paginate($perPage);

            // Devuelvo la colección con data + meta
            return new ProductoCollection($productos);
        }

        if ($request->query('vencimientos')) {
            $perPage = $request->input('per_page', 100);
            $mesFull = $request->input('mes');         // formato esperado: "2025-08" (YYYY-MM)
            $anio = $request->input('anio');          // formato: "2025"
            $mesSolo = $request->input('mes_sin_anio'); // formato: "08"

            // Inicio el query cargando la relación
            $query = Producto::with('vencimientos');

            // Aplicar filtros sobre la relación vencimientos
            if ($mesFull || $anio || $mesSolo) {
                $query->whereHas('vencimientos', function($q) use ($mesFull, $anio, $mesSolo) {
                    // 1) Filtrar por YYYY-MM exacto
                    if ($mesFull) {
                        // Si tu campo es exactamente "YYYY-MM" esto sirve:
                        $q->where('fecha_vencimiento', $mesFull);
                        return;
                    }

                    // 2) Filtrar por año completo (YYYY-*)
                    if ($anio) {
                        // si fecha_vencimiento está guardada como "YYYY-MM", usar like
                        $q->where('fecha_vencimiento', 'like', "$anio-%");
                        return;
                    }

                    // 3) Filtrar por mes sin importar el año (MM)
                    if ($mesSolo) {
                        // Usamos SUBSTR para sacar los 2 chars del mes; funciona en MySQL
                        // Esto evita falsos matches y es más explícito que un %-$mesSolo
                        $q->whereRaw('SUBSTR(fecha_vencimiento, 6, 2) = ?', [$mesSolo]);
                        return;
                    }
                });
            } else {
                // Si no viene ningún filtro, al menos aseguro que tenga algún vencimiento
                $query->whereHas('vencimientos');
            }

            // Paginación
            $productos = $query->paginate($perPage);

            // Devuelvo la colección con data + meta
            return new ProductoCollection($productos);
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
                'message' => 'Error al crear el producto',
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
    
    public function indexPos($codigo)
    {
        $producto = Producto::with([
            'vencimientos' => function($q) {
                $q->orderBy('fecha_vencimiento');
            },
            'ofertas'
        ])
            ->where("codigo_barras", $codigo)
            ->first();

        if (! $producto) {
        return response()->json(["mensaje" => "Producto no encontrado"], 404);
        }

        return response()->json($producto);
    }
    
    public function stockReporte(Request $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);
        $busqueda = $request->input('busqueda');          // string de búsqueda (opcional)
        $departamentoId = $request->input('departamento'); // id de departamento (opcional)
        $sort = $request->input('sort', 'stock_asc');     // 'stock_asc' | 'stock_desc' | 'nombre' | 'departamento'

        // Query builder: productos LEFT JOIN vencimientos + departamentos
        $query = DB::table('productos as p')
            ->leftJoin('producto_vencimientos as v', 'v.producto_id', '=', 'p.id')
            ->leftJoin('departamentos as d', 'p.departamento_id', '=', 'd.id')
            ->select(
                'p.id',
                'p.nombre',
                'p.codigo_barras',
                'p.precio',
                'p.departamento_id',
                // sumamos las cantidades (puede venir NULL si no hay vencimientos -> COALESCE 0)
                DB::raw('COALESCE(SUM(v.cantidad), 0) as stock_total'),
                // devolvemos el nombre del departamento con alias "departamento"
                DB::raw('d.nombre as departamento')
            );

        // Filtro por búsqueda (si viene)
        if (!empty($busqueda)) {
            $query->where(function($q) use ($busqueda) {
                $q->where('p.nombre', 'like', "%{$busqueda}%")
                ->orWhere('p.codigo_barras', 'like', "%{$busqueda}%");
            });
        }

        // Filtro por departamento (si viene)
        if (!empty($departamentoId)) {
            $query->where('p.departamento_id', $departamentoId);
        }

        // Agrupamos por producto (y por nombre departamento para poder seleccionar/ordenar)
        $query->groupBy('p.id', 'p.nombre', 'p.codigo_barras', 'p.precio', 'p.departamento_id', 'd.nombre');

       switch ($sort) {
            case 'stock_desc':
                $query->orderBy('stock_total', 'desc')->orderBy('p.id', 'asc');
                break;
        
            case 'nombre':
                $query->orderBy('p.nombre', 'asc');
                break;
        
            case 'departamento':
                $query->orderBy('d.nombre', 'asc')->orderBy('p.nombre', 'asc');
                break;
        
            case 'stock_asc':
            default:
                $query->orderBy('stock_total', 'asc')->orderBy('p.id', 'asc');
                break;
        }


        // Paginación (Query Builder)
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($result);
    }


    public function consulta(Request $request)
    {
        $busqueda = $request->input('busqueda');

        $producto = Producto::with('ofertas')
            ->where('codigo_barras', $busqueda)
            ->first();

        if (!$producto) {
            return response()->json([
                'data' => []
            ]);
        }

        return response()->json([
            'data' => [
                new ProductoConsultaResource($producto)
            ]
        ]);
    }
}
