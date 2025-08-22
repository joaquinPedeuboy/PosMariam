<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Venta;
use App\Models\Oferta;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ProductoVencimiento;
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
    // Método de venta con vencimientos y/o oferta
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // 🔹 Validar la solicitud
            $request->validate([
                'total' => 'required|numeric',
                'productos' => 'required|array',
                'productos.*.id' => 'required|exists:productos,id',
                'productos.*.cantidad' => 'required|integer|min:1',
                'productos.*.usar_oferta' => 'sometimes|boolean', // Opcional
            ]);
    
            $productosConStockInsuficiente = [];
            $vencimientosAEliminar = [];
            $ofertasAEliminar     = [];
    
            // 🔹 Verificar stock antes de registrar la venta
            foreach ($request->productos as $productoVendido) {
                $producto = Producto::findOrFail($productoVendido['id']);
                $cantidadOferta = $productoVendido['cantidad_oferta'];
                $cantidadVencimientos = $productoVendido['cantidad_vencimientos'];
                $usarOferta = $productoVendido['usar_oferta'] ?? false;

                // Obtener las cantidades disponibles
                $stockOferta = $producto->ofertas ? $producto->ofertas->cantidad : 0; // Stock de la oferta
                $stockVencimientos = $producto->vencimientos()->sum('cantidad'); // Stock de vencimientos

                // Verificar stock suficiente para la oferta
                if ($usarOferta && $cantidadOferta > 0 && $stockOferta < $cantidadOferta) {
                    $productosConStockInsuficiente[] = $producto->nombre . " (Oferta)";
                }

                // Verificar stock suficiente para los vencimientos
                if (!$usarOferta && $cantidadVencimientos > 0 && $stockVencimientos < $cantidadVencimientos) {
                    $productosConStockInsuficiente[] = $producto->nombre . " (Vencimientos)";
                }
            }
    
            // Si hay productos con stock insuficiente, retornar error
            if (!empty($productosConStockInsuficiente)) {
                return response()->json([
                    'error' => "Stock insuficiente para los siguientes productos:",
                    'productos' => $productosConStockInsuficiente
                ], 400);
            }
    
            // 🔹 Registrar la venta
            $venta = Venta::create([
                'total' => $request->total
            ]);
    
            // 🔹 Procesar cada producto vendido
            foreach ($request->productos as $productoVendido) {
                $producto = Producto::with('ofertas', 'vencimientos')->findOrFail($productoVendido['id']);
                $cantidadTotal = $productoVendido['cantidad'];
                $usarOferta = $productoVendido['usar_oferta'] ?? false;
    
                $cantidadOferta = 0;
                $cantidadVencimientos = $cantidadTotal;
    
                // 🔹 Descontar de la oferta si aplica
                if ($usarOferta && $producto->ofertas && $producto->ofertas->cantidad > 0) {
                    // Verificar si hay suficiente stock de la oferta
                    $cantidadOferta = min($cantidadTotal, $producto->ofertas->cantidad);
                    
                    if ($cantidadOferta > 0) {
                        // Descontar el stock de la oferta
                        $producto->ofertas->cantidad -= $cantidadOferta;
                        $producto->ofertas->save();

                        if ($producto->ofertas->cantidad <= 0) {
                            $ofertasAEliminar[] = $producto->ofertas->id;
                        }

                        // Registrar los productos vendidos con la oferta
                        $venta->productos()->attach($producto->id, [
                            'cantidad' => $cantidadOferta,
                            'nombre_producto' => $producto->nombre,
                            'precio_unitario' => $producto->ofertas->precio_oferta,
                            'subtotal' => $producto->ofertas->precio_oferta * $cantidadOferta,
                            'vencimiento_id' => null,
                        ]);
                    }

                    // Calcular la cantidad restante a descontar del stock normal
                    $cantidadRestante = $cantidadTotal - $cantidadOferta;
                    } else {
                        // Si no se usa la oferta, toda la cantidad se vende como producto normal
                        $cantidadRestante = $cantidadTotal;
                    }

                    // 🔹 Descontar de los vencimientos si hay stock restante
                    if ($cantidadRestante > 0) {
                        $vencimientos = $producto->vencimientos()->orderBy('fecha_vencimiento')->get();
                        $cantidadRestanteVencimientos = $cantidadRestante;

                        foreach ($vencimientos as $vencimiento) {
                            if ($cantidadRestanteVencimientos <= 0) break;

                            $aDescontar = min($cantidadRestanteVencimientos, $vencimiento->cantidad);
                            $vencimiento->cantidad -= $aDescontar;
                            $vencimiento->save();

                            if ($vencimiento->cantidad <= 0) {
                                $vencimientosAEliminar[] = $vencimiento->id;
                            }

                            $venta->productos()->attach($producto->id, [
                                'cantidad' => $aDescontar,
                                'nombre_producto' => $producto->nombre,
                                'precio_unitario' => $producto->precio,
                                'subtotal' => $producto->precio * $aDescontar,
                                'vencimiento_id' => $vencimiento->id,
                            ]);
                            $cantidadRestanteVencimientos -= $aDescontar;
                        }
                    }
            }
            ProductoVencimiento::destroy($vencimientosAEliminar);
            Oferta::destroy($ofertasAEliminar);

            DB::commit();
            return response()->json([
                'message' => 'Venta registrada correctamente',
                'venta' => $venta->load('productos'),
            ], 201);
    
        } catch (\Throwable $th) {
            DB::rollBack();
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
