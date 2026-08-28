import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, Link, router } from '@inertiajs/react';
import { ReactElement, SubmitEventHandler, ChangeEvent } from 'react';
import { Categoria, Imagen } from '@/types';
import CategoriaCheckboxTree from '@/components/CategoriaCheckboxTree';

interface ProductoEcommerceDetalle {
    id: number;
    slug: string;
    descripcion: string | null;
    precio_online: number | null;
    precio_oferta: number | null;
    stock_maximo_web: number | null;
    activo: boolean;
    destacado: boolean;
    meta_title: string | null;
    meta_description: string | null;
    producto: {
        nombre: string;
        codigo_barras: string | null;
        stock_pos: number;
        imagenes: Imagen[];
    };
    categorias: Categoria[];
    grupo_variante_id: number | null;
    variante_tamano: string | null;
    variante_color_nombre: string | null;
    variante_color_hex: string | null;
}

interface GrupoVariante {
    id: number;
    nombre: string;
}

interface Props {
    productoEcommerce: ProductoEcommerceDetalle;
    categorias: Categoria[];
    gruposVariantes: GrupoVariante[];
}

function Edit({ productoEcommerce: pe, categorias, gruposVariantes }: Props) {
    const { data, setData, transform, post, processing, errors } = useForm({
        _method: 'put',
        nombre: pe.producto.nombre,
        codigo_barras: pe.producto.codigo_barras ?? '',
        descripcion: pe.descripcion ?? '',
        precio_online: pe.precio_online ?? ('' as number | ''),
        precio_oferta: pe.precio_oferta ?? ('' as number | ''),
        stock_maximo_web: pe.stock_maximo_web ?? ('' as number | ''),
        activo: pe.activo,
        destacado: pe.destacado,
        meta_title: pe.meta_title ?? '',
        meta_description: pe.meta_description ?? '',
        categorias: pe.categorias.map((c) => c.id),
        imagenes: [] as File[],
        imagenes_eliminar: [] as number[],
        grupo_variante_id: pe.grupo_variante_id ?? ('' as number | 'nuevo' | ''),
        nuevo_grupo_nombre: '',
        variante_tamano: pe.variante_tamano ?? '',
        variante_color_nombre: pe.variante_color_nombre ?? '',
        variante_color_hex: pe.variante_color_hex ?? '',
    });
    
    const toggleEliminarImagen = (imagenId: number) => {
        setData(
            'imagenes_eliminar',
            data.imagenes_eliminar.includes(imagenId)
                ? data.imagenes_eliminar.filter((id) => id !== imagenId)
                : [...data.imagenes_eliminar, imagenId]
        );
    };

    const toggleCategoria = (id: number) => {
        setData(
            'categorias',
            data.categorias.includes(id)
                ? data.categorias.filter((c) => c !== id)
                : [...data.categorias, id]
        );
    };

    const handleImagenes = (e: ChangeEvent<HTMLInputElement>) => {
        setData('imagenes', e.target.files ? Array.from(e.target.files) : []);
    };

    const submit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        transform((data) => ({
            ...data,
            grupo_variante_id: data.grupo_variante_id === 'nuevo' ? '' : data.grupo_variante_id,
        }));
        post(`/admin/products/${pe.id}`, { forceFormData: true });
    };

    const eliminarImagen = (imagenId: number) => {
        if (!confirm('¿Eliminar esta imagen?')) return;
        router.delete(`/admin/images/${imagenId}`, { preserveScroll: true });
    };

    return (
        <div className="max-w-2xl">
            <h1 className="text-2xl font-semibold tracking-tight mb-1">Editar producto</h1>
            <p className="text-stone-500 mb-6">{pe.producto.nombre}</p>

            {pe.producto.imagenes.length > 0 && (
                <div className="flex flex-wrap gap-3 mb-6">
                    {pe.producto.imagenes.map((img) => {
                        const marcada = data.imagenes_eliminar.includes(img.id);

                        return (
                            <div key={img.id} className="relative">
                                <img
                                    src={`/storage/${img.url}`}
                                    className={`w-20 h-20 object-cover rounded-md border ${
                                        marcada ? 'opacity-30 border-red-300' : 'border-stone-200'
                                    }`}
                                />
                                <button
                                    type="button"
                                    onClick={() => toggleEliminarImagen(img.id)}
                                    className={`absolute -top-2 -right-2 text-white text-xs w-5 h-5 rounded-full ${
                                        marcada ? 'bg-stone-500' : 'bg-red-600'
                                    }`}
                                    title={marcada ? 'Deshacer' : 'Marcar para eliminar'}
                                >
                                    {marcada ? '↺' : '×'}
                                </button>
                                {marcada && (
                                    <p className="text-[10px] text-red-600 text-center mt-1">Se eliminará</p>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-stone-700 mb-1">
                            Nombre del producto
                        </label>
                        <input
                            type="text"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                        />
                        {errors.nombre && <p className="text-red-600 text-sm mt-1">{errors.nombre}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-stone-700 mb-1">
                            Código de barras
                        </label>
                        <input
                            type="text"
                            value={data.codigo_barras}
                            onChange={(e) => setData('codigo_barras', e.target.value)}
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                        />
                        {errors.codigo_barras && (
                            <p className="text-red-600 text-sm mt-1">{errors.codigo_barras}</p>
                        )}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">URL del producto</label>
                    <p className="text-sm text-stone-600 bg-stone-50 border border-stone-200 rounded-md px-3 py-2">
                        /productos/{pe.slug}
                    </p>
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">
                        Descripción
                    </label>
                    <textarea
                        value={data.descripcion}
                        onChange={(e) => setData('descripcion', e.target.value)}
                        rows={4}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-stone-700 mb-1">
                            Precio online
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            value={data.precio_online}
                            onChange={(e) =>
                                setData('precio_online', e.target.value ? Number(e.target.value) : '')
                            }
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-stone-700 mb-1">
                            Precio oferta online (opcional)
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            value={data.precio_oferta}
                            onChange={(e) =>
                                setData('precio_oferta', e.target.value ? Number(e.target.value) : '')
                            }
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                        />
                        {errors.precio_oferta && (
                            <p className="text-red-600 text-sm mt-1">{errors.precio_oferta}</p>
                        )}
                        {!errors.precio_oferta &&
                            data.precio_oferta !== '' &&
                            data.precio_online !== '' &&
                            Number(data.precio_oferta) >= Number(data.precio_online) && (
                                <p className="text-amber-600 text-xs mt-1">
                                    El precio de oferta no es menor al precio online.
                                </p>
                            )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-stone-700 mb-1">
                            Tope de stock web (opcional)
                        </label>
                        <input
                            type="number"
                            value={data.stock_maximo_web}
                            onChange={(e) =>
                                setData('stock_maximo_web', e.target.value ? Number(e.target.value) : '')
                            }
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                        />
                        <p className="text-xs text-stone-500 mt-1">
                            Stock real en el local: <strong>{pe.producto.stock_pos}</strong> unidades.
                            El tope nunca puede superar este número.
                        </p>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-2">
                        Categorías
                    </label>
                    <CategoriaCheckboxTree
                        categorias={categorias}
                        seleccionadas={data.categorias}
                        onToggle={toggleCategoria}
                    />
                </div>

                <div className="border border-stone-200 rounded-md p-4">
                    <label className="block text-sm font-medium text-stone-700 mb-2">
                        Variantes (opcional)
                    </label>
                    <p className="text-xs text-stone-500 mb-3">
                        Si este producto es otra presentación de algo que ya publicaste (ej: el mismo
                        perfume en 50ml y 100ml, o el mismo producto en otro color), agrupalos para que
                        el cliente pueda elegir entre ellos en la ficha.
                    </p>

                    <select
                        value={data.grupo_variante_id}
                        onChange={(e) => {
                            const val = e.target.value;
                            setData(
                                'grupo_variante_id',
                                val === 'nuevo' ? 'nuevo' : val ? Number(val) : ''
                            );
                        }}
                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm mb-3"
                    >
                        <option value="">Sin grupo (producto normal)</option>
                        {gruposVariantes.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.nombre}
                            </option>
                        ))}
                        <option value="nuevo">+ Crear nuevo grupo</option>
                    </select>
                    {errors.grupo_variante_id && (
                        <p className="text-red-600 text-sm mb-3">{errors.grupo_variante_id}</p>
                    )}

                    {data.grupo_variante_id === 'nuevo' && (
                        <input
                            type="text"
                            value={data.nuevo_grupo_nombre}
                            onChange={(e) => setData('nuevo_grupo_nombre', e.target.value)}
                            placeholder="Nombre del grupo (ej: Chanel N°5)"
                            className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm mb-3"
                        />
                    )}

                    {data.grupo_variante_id !== '' && (
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs text-stone-500 mb-1">
                                    Tamaño (opcional)
                                </label>
                                <input
                                    type="text"
                                    value={data.variante_tamano}
                                    onChange={(e) => setData('variante_tamano', e.target.value)}
                                    placeholder="Ej: 50ml"
                                    className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="block text-xs text-stone-500 mb-1">
                                    Color / Tono (opcional)
                                </label>
                                <input
                                    type="text"
                                    value={data.variante_color_nombre}
                                    onChange={(e) => setData('variante_color_nombre', e.target.value)}
                                    placeholder="Ej: 330 o Tono 4"
                                    className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                                />
                            </div>
                            {data.variante_color_nombre && (
                                <div className="col-span-2">
                                    <label className="block text-xs text-stone-500 mb-1">
                                        Color real (para el círculo del selector)
                                    </label>
                                    <input
                                        type="color"
                                        value={data.variante_color_hex || '#000000'}
                                        onChange={(e) => setData('variante_color_hex', e.target.value)}
                                        className="h-9 w-16 border border-stone-300 rounded-md"
                                    />
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">
                        Agregar imágenes
                    </label>
                    <input
                        type="file"
                        multiple
                        accept="image/*"
                        onChange={handleImagenes}
                        className="w-full text-sm"
                    />
                </div>

                <div className="flex gap-6">
                    <label className="flex items-center gap-2 text-sm text-stone-700">
                        <input
                            type="checkbox"
                            checked={data.activo}
                            onChange={(e) => setData('activo', e.target.checked)}
                        />
                        Publicado (visible en la web)
                    </label>
                    <label className="flex items-center gap-2 text-sm text-stone-700">
                        <input
                            type="checkbox"
                            checked={data.destacado}
                            onChange={(e) => setData('destacado', e.target.checked)}
                        />
                        Destacado
                    </label>
                </div>

                <div className="flex items-center gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 transition-colors disabled:opacity-50"
                    >
                        Guardar cambios
                    </button>
                    <Link href="/admin/products" className="text-sm text-stone-600 hover:underline">
                        Cancelar
                    </Link>
                </div>
            </form>
        </div>
    );
}

Edit.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Edit;