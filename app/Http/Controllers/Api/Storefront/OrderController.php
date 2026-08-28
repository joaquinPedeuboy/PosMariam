<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductoEcommerce;
use App\Services\MercadoPagoService;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request, OrderCancellationService $cancelacion)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_ecommerce_id' => 'required|exists:producto_ecommerce,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'nombre_contacto' => 'required|string|max:150',
            'telefono_contacto' => 'required|string|max:30',
            'email_contacto' => 'required|email|max:150',
            'direccion_envio' => 'required|string|max:255',
            'referencia_direccion' => 'nullable|string|max:255',
            'ciudad' => 'required|string|max:100',
            'provincia' => 'required|string|max:100',
            'codigo_postal' => 'nullable|string|max:20',
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $itemsParaCrear = [];

            foreach ($validated['items'] as $itemInput) {
                $productoEcommerce = ProductoEcommerce::where('id', $itemInput['producto_ecommerce_id'])
                    ->where('activo', true)
                    ->lockForUpdate() // evita condiciones de carrera si dos checkouts simultáneos verifican el mismo producto
                    ->firstOrFail();

                $cantidad = $itemInput['cantidad'];

                if ($cantidad > $productoEcommerce->stock_web) {
                    throw ValidationException::withMessages([
                        'items' => "No hay stock suficiente de \"{$productoEcommerce->producto->nombre}\". Disponible: {$productoEcommerce->stock_web}.",
                    ]);
                }

                $precioUnitario = $productoEcommerce->precio_actual;
                $subtotalItem = $precioUnitario * $cantidad;
                $subtotal += $subtotalItem;

                $itemsParaCrear[] = [
                    'producto_ecommerce_id' => $productoEcommerce->id,
                    'producto_id' => $productoEcommerce->producto_id,
                    'nombre_producto' => $productoEcommerce->producto->nombre,
                    'precio_unitario' => $precioUnitario,
                    'cantidad' => $cantidad,
                    'subtotal' => $subtotalItem,
                ];
            }

            $order = Order::create([
                'numero' => Order::generarNumeroUnico(),
                'customer_id' => $request->user()->id,
                'estado' => OrderStatus::PendientePago,
                'subtotal' => $subtotal,
                'total' => $subtotal, // sin costo de envío por ahora
                'nombre_contacto' => $validated['nombre_contacto'],
                'telefono_contacto' => $validated['telefono_contacto'],
                'email_contacto' => $validated['email_contacto'],
                'direccion_envio' => $validated['direccion_envio'],
                'referencia_direccion' => $validated['referencia_direccion'] ?? null,
                'ciudad' => $validated['ciudad'],
                'provincia' => $validated['provincia'],
                'codigo_postal' => $validated['codigo_postal'] ?? null,
            ]);

            foreach ($itemsParaCrear as $item) {
                $order->items()->create($item);
            }

            return $order;
        });

        return response()->json([
            'order' => [
                ...$order->load('items')->toArray(),
                'puede_cancelarse' => $cancelacion->puedeCancelarse($order),
            ],
        ], 201);
    }

    public function show(Request $request, Order $order, OrderCancellationService $cancelacion)
    {
        // Un cliente solo puede ver sus propias órdenes
        if ($order->customer_id !== $request->user()->id) {
            abort(403);
        }

        return response()->json([
            ...$order->load('items')->toArray(),
            'puede_cancelarse' => $cancelacion->puedeCancelarse($order),
        ]);
    }

    public function index(Request $request, OrderCancellationService $cancelacion)
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(10);

        $orders->getCollection()->transform(fn (Order $order) => [
            ...$order->toArray(),
            'puede_cancelarse' => $cancelacion->puedeCancelarse($order),
        ]);

        return response()->json($orders);
    }

    public function cancelar(Request $request, Order $order, OrderCancellationService $cancelacion)
    {
        if ($order->customer_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $order = $cancelacion->cancelar($order);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'order' => [
                ...$order->load('items')->toArray(),
                'puede_cancelarse' => $cancelacion->puedeCancelarse($order),
            ],
        ]);
    }

    public function crearPreferenciaPago(Request $request, Order $order, MercadoPagoService $mercadoPago)
    {
        if ($order->customer_id !== $request->user()->id) {
            abort(403);
        }

        if ($order->estado !== \App\Enums\OrderStatus::PendientePago) {
            return response()->json(['message' => 'Esta orden ya no está pendiente de pago.'], 422);
        }

        $resultado = $mercadoPago->crearPreferencia($order);

        $order->update(['mercado_pago_preference_id' => $resultado['preference_id']]);

        return response()->json($resultado);
    }
}
