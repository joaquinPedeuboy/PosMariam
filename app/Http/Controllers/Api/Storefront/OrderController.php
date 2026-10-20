<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductoEcommerce;
use App\Services\MercadoPagoService;
use App\Services\OrderCancellationService;
use App\Services\OrderStockReservationService;
use App\Services\ShippingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request, OrderCancellationService $cancelacion, ShippingCostService $envios, OrderStockReservationService $reservasStock)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_ecommerce_id' => [
                'required',
                'integer',
                'distinct',
                'exists:producto_ecommerce,id',
            ],
            'items.*.cantidad' => 'required|integer|min:1',
            'nombre_contacto' => 'required_if:metodo_entrega,retiro_local|nullable|string|max:150',
            'telefono_contacto' => 'required_if:metodo_entrega,retiro_local|nullable|string|max:30',
            'email_contacto' => 'required|email|max:150',
            'metodo_entrega' => 'required|in:motomensajeria,retiro_local',
            'customer_address_id' => [
                'required_if:metodo_entrega,motomensajeria',
                'nullable',
                'integer',
            ],
        ]);

        $direccion = null;

        if ($validated['metodo_entrega'] === 'motomensajeria') {
            $direccion = $request->user()
                ->addresses()
                ->findOrFail($validated['customer_address_id']);
        }

        $order = DB::transaction(function () use ($validated, $request, $envios, $direccion, $reservasStock) {
            $subtotal = 0;
            $itemsParaCrear = [];

            foreach ($validated['items'] as $itemInput) {
                $productoEcommerce = ProductoEcommerce::query()
                    ->whereKey($itemInput['producto_ecommerce_id'])
                    ->where('activo', true)
                    ->lockForUpdate() // evita condiciones de carrera si dos checkouts simultáneos verifican el mismo producto
                    ->firstOrFail();

                $plan = $reservasStock->prepararReserva(
                    $productoEcommerce,
                    (int) $itemInput['cantidad']
                );

                $cantidad = (int) $itemInput['cantidad'];
                $precioUnitario = (float) $plan['precio_unitario'];
                $subtotalItem = $precioUnitario * $cantidad;

                $subtotal += $subtotalItem;

                $itemsParaCrear[] = [
                    'datos' => [
                        'producto_ecommerce_id' => $productoEcommerce->id,
                        'producto_id' => $productoEcommerce->producto_id,
                        'nombre_producto' => $productoEcommerce->producto->nombre,
                        'precio_unitario' => $precioUnitario,
                        'cantidad' => $cantidad,
                        'subtotal' => $subtotalItem,
                    ],
                    'plan' => $plan,
                ];
            }

            // El envío se calcula usando los datos de la dirección guardada.
            $datosEnvio = $envios->calcular(
                $validated['metodo_entrega'],
                (float) $subtotal,
                $direccion?->ciudad,
                $direccion?->provincia,
            );

            $costoEnvio = (float) $datosEnvio['costo_envio'];
            $total = $subtotal + $costoEnvio;

            // Conservamos también la dirección en el campo de texto existente.
            $direccionTexto = null;

            if ($direccion) {
                $direccionTexto = trim(
                    $direccion->calle . ' ' . $direccion->numero
                );

                $detallesDomicilio = array_filter([
                    $direccion->torre_edificio,
                    $direccion->piso ? 'Piso ' . $direccion->piso : null,
                    $direccion->departamento
                        ? 'Departamento ' . $direccion->departamento
                        : null,
                ]);

                if (!empty($detallesDomicilio)) {
                    $direccionTexto .= ', ' . implode(', ', $detallesDomicilio);
                }
            }

            $esMotomensajeria = $validated['metodo_entrega'] === 'motomensajeria';

            $order = Order::create([
                'numero' => Order::generarNumeroUnico(),
                'customer_id' => $request->user()->id,
                // Referencia a la dirección guardada.
                'customer_address_id' => $direccion?->id,
                // Copia histórica de los datos del domicilio.
                'tipo_domicilio' => $direccion?->tipo_domicilio,
                'calle' => $direccion?->calle,
                'numero_calle' => $direccion?->numero,
                'piso' => $direccion?->piso,
                'departamento' => $direccion?->departamento,
                'torre_edificio' => $direccion?->torre_edificio,
                'informacion_adicional' => $direccion?->informacion_adicional,
                'estado' => OrderStatus::PendientePago,
                'subtotal' => $subtotal,
                'costo_envio' => $costoEnvio,
                'total' => $total,
                'metodo_entrega' => $datosEnvio['metodo_entrega'],
                
                'direccion_envio' => $esMotomensajeria
                    ? $direccionTexto
                    : 'Retiro en local',

                'referencia_direccion' => $direccion?->referencia_envio,

                'ciudad' => $esMotomensajeria
                    ? $direccion->ciudad
                    : 'Mercedes',

                'provincia' => $esMotomensajeria
                    ? $direccion->provincia
                    : 'Buenos Aires',

                'codigo_postal' => $esMotomensajeria
                    ? $direccion->codigo_postal
                    : null,

                'nombre_contacto' => $esMotomensajeria
                    ? $direccion->nombre_contacto
                    : $validated['nombre_contacto'],

                'telefono_contacto' => $esMotomensajeria
                    ? $direccion->telefono_contacto
                    : $validated['telefono_contacto'],

                'email_contacto' => $validated['email_contacto'],
            ]);

            /*
            * Creamos cada línea del pedido y sus reservas.
            * Todo sigue dentro de la misma transacción.
            */
            $expiresAt = now()->addMinutes(
                (int) config('storefront.reservation_minutes', 10)
            );

            foreach ($itemsParaCrear as $itemPreparado) {
                $item = $order->items()->create(
                    $itemPreparado['datos']
                );

                $reservasStock->crearReservas(
                    $item,
                    $itemPreparado['plan'],
                    $expiresAt
                );
            }

            $order->setAttribute('reserva_expira_at', $expiresAt);

            return $order;
        });

        return response()->json([
            'order' => [
                ...$order->load('items')->toArray(),
                'reserva_expira_at' => $order->reserva_expira_at?->toISOString(),
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

    public function crearPreferenciaPago( Request $request, Order $order, MercadoPagoService $mercadoPago, OrderCancellationService $cancelacion) {
        if ($order->customer_id !== $request->user()->id) {
            abort(403);
        }

        $reservasValidas = DB::transaction(function () use ($order) {
            $ordenBloqueada = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (
                !$ordenBloqueada ||
                $ordenBloqueada->estado !== OrderStatus::PendientePago
            ) {
                return false;
            }

            $ordenBloqueada->load('items.reservasStock');

            foreach ($ordenBloqueada->items as $item) {
                $reservas = $item->reservasStock;

                $cantidadReservada = $reservas
                    ->where('estado', \App\Models\OrderStockReservation::ESTADO_ACTIVA)
                    ->filter(fn ($reserva) =>
                        $reserva->expires_at !== null &&
                        $reserva->expires_at->isFuture()
                    )
                    ->sum('cantidad');

                if ((int) $cantidadReservada !== (int) $item->cantidad) {
                    return false;
                }
            }

            return $ordenBloqueada->items->isNotEmpty();
        });

        if (!$reservasValidas) {
            // Si todavía está pendiente, cancelar y liberar sus reservas.
            $order->refresh();

            if ($order->estado === OrderStatus::PendientePago) {
                try {
                    $cancelacion->cancelar($order);
                } catch (\RuntimeException $e) {
                    // La orden pudo cambiar de estado en paralelo.
                }
            }

            return response()->json([
                'message' => 'Las reservas de stock vencieron o ya no están disponibles. Debés generar una nueva orden.',
            ], 422);
        }

        $resultado = $mercadoPago->crearPreferencia($order->fresh('items'));

        $order->update([
            'mercado_pago_preference_id' => $resultado['preference_id'],
        ]);

        return response()->json($resultado);
    }

    public function cotizarEnvio(Request $request, ShippingCostService $envios)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.producto_ecommerce_id' => 'required|integer|distinct|exists:producto_ecommerce,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'metodo_entrega' => 'required|in:motomensajeria,retiro_local',
            'customer_address_id' => [
                'required_if:metodo_entrega,motomensajeria',
                'nullable',
                'integer',
            ],
        ]);

        $direccion = null;

        if ($validated['metodo_entrega'] === 'motomensajeria') {
            // Solo permite utilizar una dirección del cliente autenticado.
            $direccion = $request->user()
                ->addresses()
                ->findOrFail($validated['customer_address_id']);
        }

        $subtotal = 0;

        foreach ($validated['items'] as $itemInput) {
            $productoEcommerce = ProductoEcommerce::where(
                'id',
                $itemInput['producto_ecommerce_id']
            )
                ->where('activo', true)
                ->firstOrFail();

            $cantidad = $itemInput['cantidad'];

            if ($cantidad > $productoEcommerce->stock_web) {
                throw ValidationException::withMessages([
                    'items' => "No hay stock suficiente de \"{$productoEcommerce->producto->nombre}\". Disponible: {$productoEcommerce->stock_web}.",
                ]);
            }

            $subtotal += (float) $productoEcommerce->precio_actual * $cantidad;
        }

        $datosEnvio = $envios->calcular(
            $validated['metodo_entrega'],
            (float) $subtotal,
            $direccion?->ciudad,
            $direccion?->provincia,
        );

        $costoEnvio = (float) $datosEnvio['costo_envio'];

        return response()->json([
            'cotizacion' => [
                'subtotal' => round($subtotal, 2),
                'metodo_entrega' => $datosEnvio['metodo_entrega'],
                'nombre_metodo' => $datosEnvio['nombre_metodo'],
                'costo_envio' => round($costoEnvio, 2),
                'total' => round($subtotal + $costoEnvio, 2),
            ],
        ]);
    }
}
