import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, Link } from '@inertiajs/react';
import { ReactElement, SubmitEventHandler } from 'react';

interface Categoria {
    id: number;
    nombre: string;
    slug: string;
    categoria_padre_id: number | null;
    orden: number;
}

interface CategoriaPadre {
    id: number;
    nombre: string;
}

interface Props {
    categoria: Categoria;
    categoriasPadre: CategoriaPadre[];
}

function Edit({ categoria, categoriasPadre }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        nombre: categoria.nombre,
        slug: categoria.slug,
        categoria_padre_id: categoria.categoria_padre_id ?? ('' as number | ''),
        orden: categoria.orden,
    });

    const submit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        put(`/admin/categories/${categoria.id}`);
    };

    return (
        <div className="max-w-lg">
            <h1 className="text-2xl font-semibold tracking-tight mb-6">Editar categoría</h1>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">Nombre</label>
                    <input
                        type="text"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                    />
                    {errors.nombre && <p className="text-red-600 text-sm mt-1">{errors.nombre}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">Slug</label>
                    <input
                        type="text"
                        value={data.slug}
                        onChange={(e) => setData('slug', e.target.value)}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                    />
                    {errors.slug && <p className="text-red-600 text-sm mt-1">{errors.slug}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">
                        Categoría padre
                    </label>
                    <select
                        value={data.categoria_padre_id}
                        onChange={(e) =>
                            setData('categoria_padre_id', e.target.value ? Number(e.target.value) : '')
                        }
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                    >
                        <option value="">Sin categoría padre</option>
                        {categoriasPadre.map((cat) => (
                            <option key={cat.id} value={cat.id}>
                                {cat.nombre}
                            </option>
                        ))}
                    </select>
                    {errors.categoria_padre_id && (
                        <p className="text-red-600 text-sm mt-1">{errors.categoria_padre_id}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">Orden</label>
                    <input
                        type="number"
                        value={data.orden}
                        onChange={(e) => setData('orden', Number(e.target.value))}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                    />
                </div>

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 transition-colors disabled:opacity-50"
                    >
                        Guardar cambios
                    </button>
                    <Link href="/admin/categories" className="text-sm text-stone-600 hover:underline">
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    );
}

Edit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Edit;