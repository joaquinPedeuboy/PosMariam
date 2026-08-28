import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { FormEvent, ReactElement, useState } from 'react';
import { Paginated } from '@/types';

interface OrderRow {
    id: number;
    numero: string;
    estado: string;
    estado_envio: string | null;
    total: number;
    nombre_contacto: string;
    items_count: number;
    tiene_alerta_stock: boolean;
    created_at: string;
}

interface Props {
    orders: Paginated<OrderRow>;
    filtros: { estado?: string; estado_envio?: string; buscar?: string; con_alerta_stock?: string };
    estadosDisponibles: { value: string; label: string }[];
    estadosEnvioDisponibles: { value: string; label: string }[];
}

const ESTADO_COLORES: Record<string, string> = {
    pendiente_pago: 'bg-amber-100 text-amber-800',
    pagado: 'bg-green-100 text-green-800',
    fallido: 'bg-red-100 text-red-800',
    cancelado: 'bg-stone-200 text-stone-600',
};

const ENVIO_COLORES: Record<string, string> = {
    pendiente: 'bg-stone-100 text-stone-600',
    preparando: 'bg-blue-100 text-blue-800',
    enviado: 'bg-indigo-100 text-indigo-800',
    entregado: 'bg-green-100 text-green-800',
};

function Badge({ texto, colorClass }: { texto: string; colorClass: string }) {
    return (
        <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${colorClass}`}>
            {texto}
        </span>
    );
}

function Index({ orders, filtros, estadosDisponibles, estadosEnvioDisponibles }: Props) {
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');

    const aplicarFiltro = (cambios: Record<string, string>) => {
        router.get(
            '/admin/orders',
            { ...filtros, ...cambios },
            { preserveState: true, replace: true }
        );
    };

    const submitBuscar = (e: FormEvent) => {
        e.preventDefault();
        aplicarFiltro({ buscar });
    };

    return (
        <div>
            <div className="mb-6">
                <h1 className="text-2xl font-semibold tracking-tight">Pedidos</h1>
                <p className="text-stone-600 mt-1">Gestioná los pedidos y su estado de envío.</p>
            </div>

            <div className="flex flex-wrap items-center gap-3 mb-4">
                <form onSubmit={submitBuscar} className="flex items-center gap-2">
                    <input
                        type="text"
                        value={buscar}
                        onChange={(e) => setBuscar(e.target.value)}
                        placeholder="Buscar por número o cliente..."
                        className="border border-stone-300 rounded-md px-3 py-1.5 text-sm w-64"
                    />
                    <button
                        type="submit"
                        className="text-sm px-3 py-1.5 border border-stone-300 rounded-md hover:bg-stone-50"
                    >
                        Buscar
                    </button>
                </form>

                <select
                    value={filtros.estado ?? ''}
                    onChange={(e) => aplicarFiltro({ estado: e.target.value })}
                    className="border border-stone-300 rounded-md px-3 py-1.5 text-sm"
                >
                    <option value="">Todos los estados</option>
                    {estadosDisponibles.map((e) => (
                        <option key={e.value} value={e.value}>
                            {e.label}
                        </option>
                    ))}
                </select>

                <select
                    value={filtros.estado_envio ?? ''}
                    onChange={(e) => aplicarFiltro({ estado_envio: e.target.value })}
                    className="border border-stone-300 rounded-md px-3 py-1.5 text-sm"
                >
                    <option value="">Todos los envíos</option>
                    {estadosEnvioDisponibles.map((e) => (
                        <option key={e.value} value={e.value}>
                            {e.label}
                        </option>
                    ))}
                </select>

                <label className="flex items-center gap-2 text-sm text-stone-600 px-2">
                    <input
                        type="checkbox"
                        checked={filtros.con_alerta_stock === '1'}
                        onChange={(e) =>
                            aplicarFiltro({ con_alerta_stock: e.target.checked ? '1' : '' })
                        }
                    />
                    Solo con alerta de stock
                </label>
            </div>

            <div className="bg-white border border-stone-200 rounded-lg overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Número</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Cliente</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Estado</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Envío</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Total</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Fecha</th>
                            <th className="text-right px-4 py-3 font-medium text-stone-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.data.map((order) => (
                            <tr key={order.id} className="border-b border-stone-100 last:border-0">
                                <td className="px-4 py-3 font-medium">
                                    {order.numero}
                                    {order.tiene_alerta_stock && (
                                        <span
                                            className="ml-2 text-amber-600"
                                            title="Este pedido tiene items sin stock suficiente"
                                        >
                                            ⚠
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-stone-600">{order.nombre_contacto}</td>
                                <td className="px-4 py-3">
                                    <Badge
                                        texto={
                                            estadosDisponibles.find((e) => e.value === order.estado)
                                                ?.label ?? order.estado
                                        }
                                        colorClass={ESTADO_COLORES[order.estado] ?? 'bg-stone-100 text-stone-600'}
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    {order.estado_envio ? (
                                        <Badge
                                            texto={
                                                estadosEnvioDisponibles.find(
                                                    (e) => e.value === order.estado_envio
                                                )?.label ?? order.estado_envio
                                            }
                                            colorClass={
                                                ENVIO_COLORES[order.estado_envio] ?? 'bg-stone-100 text-stone-600'
                                            }
                                        />
                                    ) : (
                                        <span className="text-stone-300">—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-stone-600">
                                    ${Number(order.total).toLocaleString('es-AR')}
                                </td>
                                <td className="px-4 py-3 text-stone-500">
                                    {new Date(order.created_at).toLocaleDateString('es-AR')}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link
                                        href={`/admin/orders/${order.id}`}
                                        className="text-amber-700 hover:underline"
                                    >
                                        Ver detalle
                                    </Link>
                                </td>
                            </tr>
                        ))}
                        {orders.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-8 text-center text-stone-400">
                                    No se encontraron pedidos con esos filtros.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {orders.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4">
                    {orders.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveState
                            className={`px-3 py-1.5 text-sm rounded-md border ${
                                link.active
                                    ? 'bg-stone-900 text-white border-stone-900'
                                    : 'border-stone-300 text-stone-600 hover:bg-stone-50'
                            } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

Index.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Index;
