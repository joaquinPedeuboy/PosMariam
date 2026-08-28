import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { FormEvent, ReactElement } from 'react';

interface OrderItem {
    id: number;
    nombre_producto: string;
    precio_unitario: number;
    cantidad: number;
    faltante: number | null;
    subtotal: number;
}

interface OrderDetalle {
    id: number;
    numero: string;
    estado: string;
    estado_envio: string | null;
    subtotal: number;
    total: number;
    nombre_contacto: string;
    telefono_contacto: string;
    email_contacto: string;
    direccion_envio: string;
    referencia_direccion: string | null;
    ciudad: string;
    provincia: string;
    codigo_postal: string | null;
    transportista: string | null;
    numero_seguimiento: string | null;
    mercado_pago_payment_id: string | null;
    pagado_at: string | null;
    enviado_at: string | null;
    entregado_at: string | null;
    cancelado_at: string | null;
    created_at: string;
    items: OrderItem[];
}

interface Props {
    order: OrderDetalle;
    estadosDisponibles: { value: string; label: string }[];
    estadosEnvioDisponibles: { value: string; label: string }[];
    puedeCancelarse: boolean;
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente_pago: 'bg-amber-100 text-amber-800',
    pagado: 'bg-green-100 text-green-800',
    fallido: 'bg-red-100 text-red-800',
    cancelado: 'bg-stone-200 text-stone-600',
};

function Show({ order, estadosDisponibles, estadosEnvioDisponibles, puedeCancelarse }: Props) {
    const envioForm = useForm({
        estado_envio: order.estado_envio ?? 'pendiente',
        transportista: order.transportista ?? '',
        numero_seguimiento: order.numero_seguimiento ?? '',
    });

    const guardarEnvio = (e: FormEvent) => {
        e.preventDefault();
        envioForm.patch(`/admin/orders/${order.id}/envio`, { preserveScroll: true });
    };

    const cancelarPedido = () => {
        if (
            !confirm(
                order.estado === 'pagado'
                    ? `¿Cancelar el pedido ${order.numero}? Se repone el stock automáticamente. El reembolso en Mercado Pago hay que hacerlo a mano.`
                    : `¿Cancelar el pedido ${order.numero}?`
            )
        ) {
            return;
        }
        router.patch(`/admin/orders/${order.id}/cancelar`, {}, { preserveScroll: true });
    };

    const esGestionableEnvio = order.estado === 'pagado';
    const estadoLabel = estadosDisponibles.find((e) => e.value === order.estado)?.label ?? order.estado;

    return (
        <div className="max-w-3xl">
            <Link href="/admin/orders" className="text-sm text-stone-500 hover:underline">
                ← Volver a pedidos
            </Link>

            <div className="flex items-center justify-between mt-2 mb-6">
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">Pedido {order.numero}</h1>
                    <span
                        className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${
                            ESTADO_COLORES[order.estado] ?? 'bg-stone-100 text-stone-600'
                        }`}
                    >
                        {estadoLabel}
                    </span>
                </div>
                <span className="text-sm text-stone-500">
                    {new Date(order.created_at).toLocaleString('es-AR')}
                </span>
            </div>

            <div className="grid grid-cols-2 gap-6 mb-6">
                <div className="bg-white border border-stone-200 rounded-lg p-4">
                    <h2 className="text-sm font-semibold text-stone-700 mb-3">
                        Datos de contacto y envío
                    </h2>
                    <dl className="text-sm space-y-1 text-stone-600">
                        <div>
                            <dt className="inline font-medium text-stone-800">Nombre: </dt>
                            <dd className="inline">{order.nombre_contacto}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-stone-800">Teléfono: </dt>
                            <dd className="inline">{order.telefono_contacto}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-stone-800">Email: </dt>
                            <dd className="inline">{order.email_contacto}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-stone-800">Dirección: </dt>
                            <dd className="inline">
                                {order.direccion_envio}, {order.ciudad}, {order.provincia}{' '}
                                {order.codigo_postal}
                            </dd>
                        </div>
                        {order.referencia_direccion && (
                            <div>
                                <dt className="inline font-medium text-stone-800">Referencia: </dt>
                                <dd className="inline">{order.referencia_direccion}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                <div className="bg-white border border-stone-200 rounded-lg p-4">
                    <h2 className="text-sm font-semibold text-stone-700 mb-3">Pago</h2>
                    <dl className="text-sm space-y-1 text-stone-600">
                        <div>
                            <dt className="inline font-medium text-stone-800">Total: </dt>
                            <dd className="inline">${Number(order.total).toLocaleString('es-AR')}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-stone-800">ID de pago MP: </dt>
                            <dd className="inline">{order.mercado_pago_payment_id ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="inline font-medium text-stone-800">Pagado el: </dt>
                            <dd className="inline">
                                {order.pagado_at
                                    ? new Date(order.pagado_at).toLocaleString('es-AR')
                                    : '—'}
                            </dd>
                        </div>
                        {order.cancelado_at && (
                            <div>
                                <dt className="inline font-medium text-stone-800">Cancelado el: </dt>
                                <dd className="inline">
                                    {new Date(order.cancelado_at).toLocaleString('es-AR')}
                                </dd>
                            </div>
                        )}
                    </dl>

                    {puedeCancelarse && (
                        <button
                            onClick={cancelarPedido}
                            className="mt-3 text-sm text-red-600 hover:underline"
                        >
                            Cancelar pedido
                        </button>
                    )}
                </div>
            </div>

            <div className="bg-white border border-stone-200 rounded-lg overflow-hidden mb-6">
                <table className="w-full text-sm">
                    <thead className="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Producto</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Precio unit.</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Cantidad</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {order.items.map((item) => (
                            <tr key={item.id} className="border-b border-stone-100 last:border-0">
                                <td className="px-4 py-3">
                                    {item.nombre_producto}
                                    {item.faltante && (
                                        <p className="text-xs text-amber-600 mt-0.5">
                                            ⚠ Faltaron {item.faltante} sin stock al confirmar el pago
                                        </p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-stone-600">
                                    ${Number(item.precio_unitario).toLocaleString('es-AR')}
                                </td>
                                <td className="px-4 py-3 text-stone-600">{item.cantidad}</td>
                                <td className="px-4 py-3 text-stone-600">
                                    ${Number(item.subtotal).toLocaleString('es-AR')}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {esGestionableEnvio && (
                <form onSubmit={guardarEnvio} className="bg-white border border-stone-200 rounded-lg p-4 max-w-md">
                    <h2 className="text-sm font-semibold text-stone-700 mb-1">Envío</h2>
                    <p className="text-xs text-stone-400 mb-3">
                        Si el transportista te dio un código de tracking, cargalo abajo. Si lo mandás
                        por encomienda y no tenés uno, dejalo vacío: se genera un código propio al
                        marcar "Enviado".
                    </p>
                    <select
                        value={envioForm.data.estado_envio}
                        onChange={(e) => envioForm.setData('estado_envio', e.target.value)}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm mb-2"
                    >
                        {estadosEnvioDisponibles.map((e) => (
                            <option key={e.value} value={e.value}>
                                {e.label}
                            </option>
                        ))}
                    </select>
                    <input
                        type="text"
                        placeholder="Transportista (opcional, ej: Andreani, OCA, encomienda...)"
                        value={envioForm.data.transportista}
                        onChange={(e) => envioForm.setData('transportista', e.target.value)}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm mb-2"
                    />
                    <input
                        type="text"
                        placeholder="Código de tracking (opcional)"
                        value={envioForm.data.numero_seguimiento}
                        onChange={(e) => envioForm.setData('numero_seguimiento', e.target.value)}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm mb-3"
                    />
                    <button
                        type="submit"
                        disabled={envioForm.processing}
                        className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 disabled:opacity-50"
                    >
                        Guardar envío
                    </button>
                    <div className="text-xs text-stone-500 mt-3 space-y-0.5">
                        <p>
                            Enviado:{' '}
                            {order.enviado_at ? new Date(order.enviado_at).toLocaleString('es-AR') : '—'}
                        </p>
                        <p>
                            Entregado:{' '}
                            {order.entregado_at
                                ? new Date(order.entregado_at).toLocaleString('es-AR')
                                : '—'}
                        </p>
                    </div>
                </form>
            )}
        </div>
    );
}

Show.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Show;
