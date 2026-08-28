import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router, useForm } from '@inertiajs/react';
import { ReactElement, FormEvent } from 'react';
import { Paginated } from '@/types';

interface ProductoFila {
    id: number;
    nombre: string;
    slug: string;
    precio_final: number;
    precio_oferta: number | null;
    stock_web: number;
    activo: boolean;
    categorias: string[];
}

interface Props {
    productos: Paginated<ProductoFila>;
    filters: { busqueda: string | null };
}

function Index({ productos, filters }: Props) {
    const { data, setData } = useForm({ busqueda: filters.busqueda ?? '' });

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/products', { busqueda: data.busqueda }, { preserveState: true });
    };

    const handleDelete = (id: number, nombre: string) => {
        if (!confirm(`¿Quitar "${nombre}" de la tienda online?`)) return;
        router.delete(`/admin/products/${id}`);
    };

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">Productos</h1>
                    <p className="text-stone-600 mt-1">
                        Productos publicados en tu tienda online.
                    </p>
                </div>
                <Link
                    href="/admin/products/create"
                    className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 transition-colors"
                >
                    Publicar producto
                </Link>
            </div>

            <form onSubmit={buscar} className="mb-4">
                <input
                    type="text"
                    value={data.busqueda}
                    onChange={(e) => setData('busqueda', e.target.value)}
                    placeholder="Buscar por nombre o código de barras..."
                    className="w-full max-w-sm border border-stone-300 rounded-md px-3 py-2 text-sm"
                />
            </form>

            <div className="bg-white border border-stone-200 rounded-lg overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Producto</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Categorías</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Precio</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Precio oferta</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Stock web</th>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">Estado</th>
                            <th className="text-right px-4 py-3 font-medium text-stone-600">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {productos.data.map((p) => (
                            <tr key={p.id} className="border-b border-stone-100 last:border-0">
                                <td className="px-4 py-3 font-medium">{p.nombre}</td>
                                <td className="px-4 py-3 text-stone-500">
                                    {p.categorias.length > 0 ? p.categorias.join(', ') : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    ${p.precio_final.toLocaleString('es-AR')}
                                </td>
                                <td className="px-4 py-3">
                                    {p.precio_oferta ? `$${p.precio_oferta.toLocaleString('es-AR')}` : 'Sin oferta'}
                                </td>
                                <td className="px-4 py-3 text-stone-500">{p.stock_web}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`text-xs font-medium px-2 py-1 rounded-full ${
                                            p.activo
                                                ? 'bg-green-50 text-green-700'
                                                : 'bg-stone-100 text-stone-500'
                                        }`}
                                    >
                                        {p.activo ? 'Publicado' : 'Oculto'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right space-x-3">
                                    <Link
                                        href={`/admin/products/${p.id}/edit`}
                                        className="text-amber-700 hover:underline"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        onClick={() => handleDelete(p.id, p.nombre)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Quitar
                                    </button>
                                </td>
                            </tr>
                        ))}
                        {productos.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-stone-400">
                                    Todavía no publicaste ningún producto en la web.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {productos.last_page > 1 && (
                <div className="flex gap-1 mt-4">
                    {productos.links.map((link, i) => (
                        <Link
                            key={i}
                            href={link.url ?? '#'}
                            preserveState
                            className={`px-3 py-1.5 text-sm rounded-md border ${
                                link.active
                                    ? 'bg-stone-900 text-white border-stone-900'
                                    : 'border-stone-200 text-stone-600 hover:bg-stone-50'
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