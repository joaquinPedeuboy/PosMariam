<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderShippingStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItemStockMovement;
use App\Models\ProductoVencimiento;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderAdminController extends Controller
{
    private const ESTADOS_PEDIDO = [
        ['value' => 'pendiente_pago', 'label' => 'Pendiente de pago'],
        ['value' => 'pagado', 'label' => 'Pagado'],
        ['value' => 'fallido', 'label' => 'Fallido'],
        ['value' => 'cancelado', 'label' => 'Cancelado'],
    ];

    public function __construct(private OrderCancellationService $cancelacion)
    {
    }

    public function index(Request $request)
    {
        $query = Order::withCount('items')->latest();

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('estado_envio')) {
            $query->where('estado_envio', $request->string('estado_envio'));
        }

        if ($request->boolean('con_alerta_stock')) {
            $query->whereHas('items', fn ($q) => $q->where('faltante', '>', 0));
        }

        if ($request->filled('buscar')) {
            $buscar = $request->string('buscar');
            $query->where(function ($q) use ($buscar) {
                $q->where('numero', 'like', "%{$buscar}%")
                ->orWhere('nombre_contacto', 'like', "%{$buscar}%");
            });
        }

        $orders = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Orders/OrdersIndex', [
            'orders' => $orders,
            'filtros' => $request->only(['estado', 'estado_envio', 'buscar', 'con_alerta_stock']),
            'estadosDisponibles' => self::ESTADOS_PEDIDO,
            'estadosEnvioDisponibles' => $this->estadosEnvioParaFrontend(),
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Admin/Orders/OrdersShow', [
            'order' => $order->load('items'),
            'estadosDisponibles' => self::ESTADOS_PEDIDO,
            'estadosEnvioDisponibles' => $this->estadosEnvioParaFrontend(),
            'puedeCancelarse' => $this->cancelacion->puedeCancelarse($order),
        ]);
    }

    public function actualizarEnvio(Request $request, Order $order)
    {
        if ($order->estado->value !== 'pagado') {
            return back()->with('error', 'Solo se puede gestionar el envío de pedidos pagados.');
        }

        $validated = $request->validate([
            'estado_envio' => 'required|in:pendiente,preparando,enviado,entregado',
            'transportista' => 'nullable|string|max:100',
            'numero_seguimiento' => 'nullable|string|max:100',
        ]);

        $order->transportista = $validated['transportista'] ?: $order->transportista;
        $order->numero_seguimiento = $validated['numero_seguimiento'] ?: $order->numero_seguimiento;
        $order->estado_envio = $validated['estado_envio'];

        if ($validated['estado_envio'] === OrderShippingStatus::Enviado->value && !$order->enviado_at) {
            $order->enviado_at = now();

            // Si no cargaron un código de tracking del transportista (ej: se
            // manda por encomienda y no dan uno formal), generamos uno propio.
            if (!$order->numero_seguimiento) {
                $order->numero_seguimiento = Order::generarNumeroSeguimientoInterno();
            }
        }

        if ($validated['estado_envio'] === OrderShippingStatus::Entregado->value && !$order->entregado_at) {
            $order->entregado_at = now();
        }

        $order->save();

        return back()->with('success', 'Envío actualizado');
    }

    /**
     * Cancela un pedido. Si ya estaba pagado, repone el stock exacto a los
     * lotes de donde salió (usando el registro de order_item_stock_movements
     * dejado por el webhook al momento de confirmar el pago).
     *
     * No permite cancelar pedidos ya enviados/entregados: a partir de ahí
     * es una devolución, un flujo distinto que no maneja este botón.
     */
    public function cancelar(Order $order)
    {
        $estabaPagado = $order->estado->value === 'pagado';

        try {
            $this->cancelacion->cancelar($order);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = $estabaPagado
            ? 'Pedido cancelado y stock repuesto. Recordá procesar el reembolso en Mercado Pago manualmente.'
            : 'Pedido cancelado.';

        return back()->with('success', $mensaje);
    }

    private function estadosEnvioParaFrontend(): array
    {
        return array_map(
            fn (OrderShippingStatus $estado) => ['value' => $estado->value, 'label' => $estado->label()],
            OrderShippingStatus::cases()
        );
    }
}
