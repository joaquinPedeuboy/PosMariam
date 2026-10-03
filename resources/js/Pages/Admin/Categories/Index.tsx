import AdminLayout from '@/Layouts/AdminLayout';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface CategoriaNodo {
    id: number;
    nombre: string;
    slug: string;
    orden: number;
    mostrar_en_menu_principal: boolean;
    cantidad_productos: number;
    hijos: CategoriaNodo[];
}

interface Props {
    categorias: CategoriaNodo[];
}

function Index({ categorias }: Props) {
    const [colapsadas, setColapsadas] = useState<Set<number>>(new Set());

    const toggle = (id: number) => {
        setColapsadas((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const handleDelete = (id: number, nombre: string) => {
        if (!confirm(`¿Eliminar la categoría "${nombre}"?`)) return;

        router.delete(`/admin/categories/${id}`);
    };

    const filas: { nodo: CategoriaNodo; nivel: number }[] = [];

    const aplanar = (nodos: CategoriaNodo[], nivel: number) => {
        for (const nodo of nodos) {
            filas.push({ nodo, nivel });

            if (nodo.hijos.length > 0 && !colapsadas.has(nodo.id)) {
                aplanar(nodo.hijos, nivel + 1);
            }
        }
    };

    aplanar(categorias, 0);

    // Solo las categorías de primer nivel pueden formar
    // parte del menú principal.
    const categoriasMenuPrincipal = categorias.filter(
        (categoria) => categoria.mostrar_en_menu_principal
    );

    const cantidadMenuPrincipal = categoriasMenuPrincipal.length;

    return (
        <div>
            {/* Encabezado */}
            <div className="flex items-start justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Categorías
                    </h1>

                    <p className="text-stone-600 mt-1">
                        Organizá los productos y el menú de tu tienda online.
                    </p>
                </div>

                <Link
                    href="/admin/categories/create"
                    className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 transition-colors"
                >
                    Nueva categoría
                </Link>
            </div>

            {/* Resumen menú principal */}
            <div className="bg-white border border-stone-200 rounded-lg px-5 py-4 mb-6">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-stone-800">
                            Menú principal
                        </p>

                        <p className="text-sm text-stone-500 mt-1">
                            Seleccioná hasta 8 categorías principales para
                            mostrar directamente en el menú.
                        </p>
                    </div>

                    <div className="shrink-0 text-right">
                        <span
                            className={`text-lg font-semibold ${
                                cantidadMenuPrincipal >= 8
                                    ? 'text-green-700'
                                    : 'text-stone-800'
                            }`}
                        >
                            {cantidadMenuPrincipal} / 8
                        </span>

                        <p className="text-xs text-stone-400">
                            categorías seleccionadas
                        </p>
                    </div>
                </div>
            </div>

            {/* Tabla */}
            <div className="bg-white border border-stone-200 rounded-lg overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-stone-50 border-b border-stone-200">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-stone-600">
                                Nombre
                            </th>

                            <th className="text-left px-4 py-3 font-medium text-stone-600">
                                Productos
                            </th>

                            <th className="text-left px-4 py-3 font-medium text-stone-600">
                                Orden
                            </th>

                            <th className="text-left px-4 py-3 font-medium text-stone-600">
                                Menú principal
                            </th>

                            <th className="text-right px-4 py-3 font-medium text-stone-600">
                                Acciones
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {filas.map(({ nodo, nivel }) => {
                            const tieneHijos = nodo.hijos.length > 0;
                            const abierta = !colapsadas.has(nodo.id);
                            const esPrincipal = nivel === 0;

                            return (
                                <tr
                                    key={nodo.id}
                                    className="border-b border-stone-100 last:border-0"
                                >
                                    {/* Nombre */}
                                    <td className="px-4 py-3 font-medium">
                                        <div
                                            className="flex items-center gap-2"
                                            style={{
                                                paddingLeft: `${nivel * 20}px`,
                                            }}
                                        >
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggle(nodo.id)
                                                }
                                                className={`w-3 text-xs text-stone-400 ${
                                                    tieneHijos
                                                        ? ''
                                                        : 'invisible'
                                                }`}
                                                aria-label={
                                                    abierta
                                                        ? 'Contraer'
                                                        : 'Expandir'
                                                }
                                            >
                                                {abierta ? '▾' : '▸'}
                                            </button>

                                            <span>{nodo.nombre}</span>
                                        </div>
                                    </td>

                                    {/* Productos */}
                                    <td className="px-4 py-3 text-stone-500">
                                        {nodo.cantidad_productos}
                                    </td>

                                    {/* Orden */}
                                    <td className="px-4 py-3 text-stone-500">
                                        {nodo.orden}
                                    </td>

                                    {/* Menú principal */}
                                    <td className="px-4 py-3">
                                        {esPrincipal ? (
                                            nodo.mostrar_en_menu_principal ? (
                                                <span className="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">
                                                    ✓ Seleccionada
                                                </span>
                                            ) : (
                                                <span className="text-xs text-stone-400">
                                                    No seleccionada
                                                </span>
                                            )
                                        ) : (
                                            <span className="text-xs text-stone-300">
                                                —
                                            </span>
                                        )}
                                    </td>

                                    {/* Acciones */}
                                    <td className="px-4 py-3 text-right space-x-3">
                                        <Link
                                            href={`/admin/categories/create?parent=${nodo.id}`}
                                            className="text-stone-600 hover:underline"
                                        >
                                            + Subcategoría
                                        </Link>

                                        <Link
                                            href={`/admin/categories/${nodo.id}/edit`}
                                            className="text-amber-700 hover:underline"
                                        >
                                            Editar
                                        </Link>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleDelete(
                                                    nodo.id,
                                                    nodo.nombre
                                                )
                                            }
                                            className="text-red-600 hover:underline"
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            );
                        })}

                        {filas.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-stone-400"
                                >
                                    Todavía no creaste ninguna categoría.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

Index.layout = (page: React.ReactElement) => (
    <AdminLayout>{page}</AdminLayout>
);

export default Index;