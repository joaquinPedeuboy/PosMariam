<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Venta;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\VentaCollection;

class VentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $fecha = $request->query('fecha');
        $total = $request->query('total');

        // Construimos la consulta con los filtros opcionales
        $query = Venta::query();

        if ($fecha) {
            $query->whereDate('created_at', $fecha);
        }

        if ($total) {
            $query->where('total', $total);
        }

        $ventas = $query->with('productos')->paginate(10);

        return new VentaCollection($ventas);
    }

    /**
     * Store a newly created resource in storage.
     */
    // Metodo de venta con vencimientos
    public function store(Request $request)
    {
        try {
            // validar la solicitud
            $request->validate([
                'total' => 'required|numeric',
                'productos' => 'required|array',
                'productos.*.id' => 'required|exists:productos,id',
                'productos.*.vencimientos' => 'required|array',
                'productos.*.vencimientos.*.cantidad' => 'required|integer|min:1',
            ]);

            // Verificar si hay suficiente stock para todos los productos
            foreach ($request->productos as $productoVendido) {
                $producto = Producto::findOrFail($productoVendido['id']);
                $cantidadNecesaria = $productoVendido['vencimientos'][0]['cantidad'];
                $stockTotal = $producto->vencimientos()->sum('cantidad');

                if ($stockTotal < $cantidadNecesaria) {
                    return response()->json([
                        'error' => "Stock insuficiente para el producto {$producto->nombre}."
                    ], 400);
                }
            }

            // Registrar la venta
            $venta = Venta::create([
                'total' => $request->total
            ]);

            // Procesar cada producto vendido
            foreach ($request->productos as $productoVendido) {
                $producto = Producto::findOrFail($productoVendido['id']);
                $cantidadNecesaria = $productoVendido['vencimientos'][0]['cantidad'];

                // Obtener vencimientos ordenados
                $vencimientos = $producto->vencimientos()->orderBy('fecha_vencimiento')->get();
                $cantidadRestante = $cantidadNecesaria;

                foreach ($vencimientos as $vencimiento) {
                    if ($cantidadRestante <= 0) break;

                    $aDescontar = min($cantidadRestante, $vencimiento->cantidad);
                    $vencimiento->cantidad -= $aDescontar;
                    $vencimiento->save();
                    $vencimiento->refresh(); // Asegura que el objeto refleje los cambios en BD
                    $cantidadRestante -= $aDescontar;

                    // Guardar en venta_productos
                    $venta->productos()->attach($producto->id, [
                        'cantidad' => $aDescontar,
                        'vencimiento_id' => $vencimiento->id,
                        'nombre_producto' => $producto->nombre,
                        'precio_unitario' => $producto->precio,
                        'subtotal' => $producto->precio * $aDescontar,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Venta registrada correctamente',
                'venta' => $venta->load('productos'),
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Error de venta',
                'details' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Venta $venta)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Venta $venta)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Venta $venta)
    {
        //
    }

    public function totalesDiarios(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $anio = $request->input('anio', now()->year);

            $query = DB::table('ventas')
                ->select(
                    DB::raw('DATE(created_at) as fecha'),
                    DB::raw("DAYNAME(created_at) as dia_ingles"),
                    DB::raw('SUM(total) as total_vendido')
                )
                ->groupBy('fecha', 'dia_ingles')
                ->orderByDesc('fecha');

                if ($fechaInicio && $fechaFin) {
                    $fechaInicio = \Carbon\Carbon::parse($fechaInicio)->startOfDay();
                    $fechaFin = \Carbon\Carbon::parse($fechaFin)->endOfDay();
                    
                    $diasDiferencia = $fechaInicio->diffInDays($fechaFin);
                    if ($diasDiferencia > 15) {
                        return response()->json([
                            'message' => 'El rango de fechas no puede superar los 15 días.',
                            'ventas' => []
                        ]);
                    }
            
                    $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
                } else {
                    // Si no se pasan las fechas, filtrar los últimos 5 días del año seleccionado
                    $fechaInicio = \Carbon\Carbon::createFromDate($anio)->startOfYear();
                    $fechaFin = \Carbon\Carbon::createFromDate($anio)->endOfYear();
                    $query->whereDate('created_at', '>=', now()->subDays(5)->startOfDay())
                        ->whereBetween('created_at', [$fechaInicio, $fechaFin]);
                }
            
                $ventas = $query->get();

            // Traducir los días de la semana
            $diasTraducidos = [
                "Monday" => "Lunes", "Tuesday" => "Martes", "Wednesday" => "Miércoles",
                "Thursday" => "Jueves", "Friday" => "Viernes", "Saturday" => "Sábado", "Sunday" => "Domingo"
            ];

            foreach ($ventas as $venta) {
                $venta->dia = $diasTraducidos[$venta->dia_ingles] ?? $venta->dia_ingles;
            }

            return response()->json($ventas);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error al cargar los datos',
                'error' => $th->getMessage(),
            ], 500);
        }

        
    }

    public function totalesMensuales(Request $request)
    {
        $anio = $request->input('anio', date('Y')); // Asegurar que el año sea correcto
        $fechaInicioMensual = $request->input('fecha_inicio');
        $fechaFinMensual = $request->input('fecha_fin');

        // Crear la consulta base para ventas mensuales
        $query = DB::table('ventas')
            ->select(DB::raw('YEAR(created_at) as año, MONTH(created_at) as mes'), DB::raw('SUM(total) as total_vendido'))
            ->groupBy('año', 'mes')
            ->whereYear('created_at', $anio);

        // Si el usuario ha proporcionado un rango de fechas
        if ($fechaInicioMensual && $fechaFinMensual) {
            $fechaInicioMensual = Carbon::createFromFormat('Y-m', $fechaInicioMensual)->startOfMonth();
            $fechaFinMensual = Carbon::createFromFormat('Y-m', $fechaFinMensual)->endOfMonth(); 
            // Aseguramos que las fechas estén dentro del mismo año
            $query->whereBetween('created_at', [$fechaInicioMensual, $fechaFinMensual]);
        } else {
            // Si no se proporciona un rango de fechas, buscamos los últimos 5 meses del año seleccionado
            $fechaInicio = Carbon::now()->subMonths(5)->startOfMonth();
            $fechaFin = Carbon::now()->endOfMonth();
            $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        }

        $ventas = $query->get();

        if ($ventas->isEmpty()) {
            return response()->json([
                'message' => 'No hay ventas registradas para el rango de fechas o el año seleccionado.',
                'ventas' => []
            ]);
        }

        return response()->json($ventas);
    }

    public function productosMasMenosVendidos(Request $request)
    {
        $año = $request->query('año', date('Y'));
        $mes = $request->query('mes', date('m'));

        // Obtener los productos más vendidos
        $productosMasVendidos = Venta::select('productos.nombre', DB::raw('SUM(venta_productos.cantidad) as cantidad_vendida'))
            ->join('venta_productos', 'ventas.id', '=', 'venta_productos.venta_id')
            ->join('productos', 'venta_productos.producto_id', '=', 'productos.id')
            ->whereYear('ventas.created_at', $año)
            ->whereMonth('ventas.created_at', $mes)
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('cantidad_vendida')
            ->limit(10)
            ->get();

        // Obtener los productos menos vendidos (excluyendo los que no se vendieron)
        $productosMenosVendidos = Venta::select('productos.nombre', DB::raw('SUM(venta_productos.cantidad) as cantidad_vendida'))
            ->join('venta_productos', 'ventas.id', '=', 'venta_productos.venta_id')
            ->join('productos', 'venta_productos.producto_id', '=', 'productos.id')
            ->whereYear('ventas.created_at', $año)
            ->whereMonth('ventas.created_at', $mes)
            ->groupBy('productos.id', 'productos.nombre')
            ->orderBy('cantidad_vendida')
            ->limit(10)
            ->get();

        if ($productosMasVendidos->isEmpty() && $productosMenosVendidos->isEmpty()) {
            return response()->json([
                'message' => 'No hay ventas registradas para el rango de fechas o el año seleccionado.',
                'productos_mas_vendidos' => [],
                'productos_menos_vendidos' => []
            ]);
        }

        return response()->json([
            'productos_mas_vendidos' => $productosMasVendidos,
            'productos_menos_vendidos' => $productosMenosVendidos
        ]);
    }
}
