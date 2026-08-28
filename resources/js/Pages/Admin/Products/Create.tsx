import AdminLayout from '@/Layouts/AdminLayout';
import { useForm, Link } from '@inertiajs/react';
import { ReactElement, SubmitEventHandler, ChangeEvent, useState, useEffect } from 'react';
import { Categoria } from '@/types';
import CategoriaCheckboxTree from '@/components/CategoriaCheckboxTree';

interface ResultadoBusqueda {
    id: number;
    nombre: string;
    codigo_barras: string | null;
}

interface GrupoVariante {
    id: number;
    nombre: string;
}

interface Props {
    categorias: Categoria[];
    gruposVariantes: GrupoVariante[];
}

function Create({ categorias, gruposVariantes }: Props) {
    const { data, setData, transform, post, processing, errors } = useForm({
        producto_id: '' as number | '',
        nombre: '',
        codigo_barras: '',
        descripcion: '',
        precio_online: '' as number | '',
        precio_oferta: '' as number | '',
        stock_maximo_web: '' as number | '',
        activo: false,
        destacado: false,
        meta_title: '',
        meta_description: '',
        categorias: [] as number[],
        imagenes: [] as File[],
        grupo_variante_id: '' as number | 'nuevo' | '',
        nuevo_grupo_nombre: '',
        variante_tamano: '',
        variante_color_nombre: '',
        variante_color_hex: '',
    });

    const [busqueda, setBusqueda] = useState('');
    const [resultados, setResultados] = useState<ResultadoBusqueda[]>([]);
    const [productoSeleccionado, setProductoSeleccionado] = useState(false);

    useEffect(() => {
        if (busqueda.length < 2 || productoSeleccionado) {
            setResultados([]);
            return;
        }
        const timeout = setTimeout(() => {
            fetch(`/admin/products/buscar-pos?q=${encodeURIComponent(busqueda)}`)
                .then((r) => r.json())
                .then(setResultados);
        }, 300);
        return () => clearTimeout(timeout);
    }, [busqueda, productoSeleccionado]);

    const seleccionarProducto = (p: ResultadoBusqueda) => {
        setData('producto_id', p.id);
        setData('nombre', p.nombre);
        setData('codigo_barras', p.codigo_barras ?? '');
        setBusqueda(p.nombre);
        setProductoSeleccionado(true);
        setResultados([]);
    };

    const limpiarSeleccion = () => {
        setData('producto_id', '');
        setData('nombre', '');
        setData('codigo_barras', '');
        setBusqueda('');
        setProductoSeleccionado(false);
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
            // 'nuevo' es solo una señal interna para mostrar el input de
            // texto — el backend no sabe qué es eso, así que no se lo mandamos.
            grupo_variante_id: data.grupo_variante_id === 'nuevo' ? '' : data.grupo_variante_id,
        }));
        post('/admin/products', { forceFormData: true });
    };

    return (
        <div className="max-w-2xl">
            <h1 className="text-2xl font-semibold tracking-tight mb-6">Publicar producto</h1>

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label className="block text-sm font-medium text-stone-700 mb-1">
                        Buscar Producto del POS
                    </label>
                    {!productoSeleccionado ? (
                        <div className="relative">
                            <input
                                type="text"
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                placeholder="Buscar por nombre o código de barras..."
                                className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                            />
                            {resultados.length > 0 && (
                                <div className="absolute z-10 w-full bg-white border border-stone-200 rounded-md mt-1 shadow-sm max-h-56 overflow-auto">
                                    {resultados.map((p) => (
                                        <button
                                            type="button"
                                            key={p.id}
                                            onClick={() => seleccionarProducto(p)}
                                            className="w-full text-left px-3 py-2 text-sm hover:bg-stone-50 border-b border-stone-100 last:border-0"
                                        >
                                            <span className="font-medium">{p.nombre}</span>
                                            {p.codigo_barras && (
                                                <span className="text-stone-400 ml-2">
                                                    {p.codigo_barras}
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="flex items-center justify-between bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                            <span className="text-sm font-medium text-amber-900">{data.nombre}</span>
                            <button
                                type="button"
                                onClick={limpiarSeleccion}
                                className="text-xs text-amber-700 hover:underline"
                            >
                                Cambiar
                            </button>
                        </div>
                    )}
                    {errors.producto_id && (
                        <p className="text-red-600 text-sm mt-1">{errors.producto_id}</p>
                    )}
                </div>

                {productoSeleccionado && (
                    <>
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
                                {errors.nombre && (
                                    <p className="text-red-600 text-sm mt-1">{errors.nombre}</p>
                                )}
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
                                    Precio online (opcional)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={data.precio_online}
                                    onChange={(e) =>
                                        setData('precio_online', e.target.value ? Number(e.target.value) : '')
                                    }
                                    placeholder="Usa el precio del POS si se deja vacío"
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
                                    placeholder="Precio de oferta"
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
                                    placeholder="Sin límite si se deja vacío"
                                    className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                                />
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
                                Si este producto es otra presentación de algo que ya publicaste (ej: el
                                mismo perfume en 50ml y 100ml, o el mismo producto en otro color),
                                agrupalos para que el cliente pueda elegir entre ellos en la ficha.
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
                                            onChange={(e) =>
                                                setData('variante_color_nombre', e.target.value)
                                            }
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
                                                onChange={(e) =>
                                                    setData('variante_color_hex', e.target.value)
                                                }
                                                className="h-9 w-16 border border-stone-300 rounded-md"
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-stone-700 mb-1">
                                Imágenes
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
                                Publicar ya (visible en la web)
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

                        <details className="border border-stone-200 rounded-md p-4">
                            <summary className="text-sm font-medium cursor-pointer">SEO (opcional)</summary>
                            <div className="mt-4 space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-stone-700 mb-1">
                                        Meta título
                                    </label>
                                    <input
                                        type="text"
                                        value={data.meta_title}
                                        onChange={(e) => setData('meta_title', e.target.value)}
                                        maxLength={60}
                                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-stone-700 mb-1">
                                        Meta descripción
                                    </label>
                                    <textarea
                                        value={data.meta_description}
                                        onChange={(e) => setData('meta_description', e.target.value)}
                                        maxLength={160}
                                        rows={2}
                                        className="w-full border border-stone-300 rounded-md px-3 py-2 text-sm"
                                    />
                                </div>
                            </div>
                        </details>

                        <div className="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="bg-stone-900 text-white text-sm font-medium px-4 py-2 rounded-md hover:bg-stone-800 transition-colors disabled:opacity-50"
                            >
                                Publicar producto
                            </button>
                            <Link href="/admin/products" className="text-sm text-stone-600 hover:underline">
                                Cancelar
                            </Link>
                        </div>
                    </>
                )}
            </form>
        </div>
    );
}

Create.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Create;