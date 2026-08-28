<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaEcommerce;
use App\Models\GrupoVariante;
use App\Models\Imagen;
use App\Models\Producto;
use App\Models\ProductoEcommerce;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Format;

class ProductAdminController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = $request->input('busqueda');

        $query = ProductoEcommerce::with(['producto', 'categorias']);

        if ($busqueda) {
            $query->whereHas('producto', function ($q) use ($busqueda) {
                $q->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('codigo_barras', 'like', "%{$busqueda}%");
            });
        }

        $productos = $query->paginate(20)->through(fn ($pe) => [
            'id' => $pe->id,
            'nombre' => $pe->producto->nombre,
            'slug' => $pe->slug,
            'precio_final' => $pe->precio_final,
            'precio_oferta' => $pe->en_oferta ? $pe->precio_oferta : null,
            'stock_web' => $pe->stock_web,
            'activo' => $pe->activo,
            'categorias' => $pe->categorias->pluck('nombre'),
        ]);

        return Inertia::render('Admin/Products/Index', [
            'productos' => $productos,
            'filters' => ['busqueda' => $busqueda],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Products/Create', [
            'productosDisponibles' => Producto::whereDoesntHave('ecommerce')
                ->get(['id', 'nombre', 'codigo_barras']),
            'categorias' => CategoriaEcommerce::orderBy('orden')->get(),
            'gruposVariantes' => GrupoVariante::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id|unique:producto_ecommerce,producto_id',
            'nombre' => 'required|string|max:255',
            'codigo_barras' => 'nullable|string|unique:productos,codigo_barras,' . $request->producto_id,
            'descripcion' => 'nullable|string',
            'precio_online' => 'nullable|numeric|min:0',
            'precio_oferta' => 'nullable|numeric|min:0',
            'stock_maximo_web' => 'nullable|integer|min:0',
            'activo' => 'boolean',
            'destacado' => 'boolean',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'categorias' => 'array',
            'categorias.*' => 'exists:categorias_ecommerce,id',
            'imagenes.*' => 'nullable|image|max:4096',
            'grupo_variante_id' => 'nullable|exists:grupo_variantes,id',
            'nuevo_grupo_nombre' => 'nullable|string|max:100',
            'variante_tamano' => 'nullable|string|max:50',
            'variante_color_nombre' => 'nullable|string|max:50',
            'variante_color_hex' => 'nullable|string|max:7',
        ]);

        $producto = Producto::findOrFail($validated['producto_id']);
 
        $this->validarPrecioOferta(
            $validated['precio_oferta'] ?? null,
            $validated['precio_online'] ?? null,
            (float) $producto->precio
        );
 
        $grupoVarianteId = $this->resolverGrupoVariante($validated);
        $this->validarDatosDeVariante($grupoVarianteId, $validated);

        DB::transaction(function () use ($validated, $request, $producto, $grupoVarianteId) {
            $producto->update([
                'nombre' => $validated['nombre'],
                'codigo_barras' => $validated['codigo_barras'] ?? $producto->codigo_barras,
            ]);

            try {
                $productoEcommerce = ProductoEcommerce::create([
                    'producto_id' => $validated['producto_id'],
                    'slug' => $this->generarSlugUnico($validated['nombre']),
                    'descripcion' => $validated['descripcion'] ?? null,
                    'precio_online' => $validated['precio_online'] ?? null,
                    'precio_oferta' => $validated['precio_oferta'] ?? null,
                    'stock_maximo_web' => $validated['stock_maximo_web'] ?? null,
                    'activo' => $validated['activo'] ?? false,
                    'destacado' => $validated['destacado'] ?? false,
                    'meta_title' => $validated['meta_title'] ?? null,
                    'meta_description' => $validated['meta_description'] ?? null,
                    'grupo_variante_id' => $grupoVarianteId,
                    'variante_tamano' => $validated['variante_tamano'] ?? null,
                    'variante_color_nombre' => $validated['variante_color_nombre'] ?? null,
                    'variante_color_hex' => $validated['variante_color_hex'] ?? null,
                ]);
            } catch (\InvalidArgumentException $e) {
                // El guard del modelo (evento saving) tira esta excepción si
                // la combinación tamaño+color ya existe en el grupo.
                throw ValidationException::withMessages(['grupo_variante_id' => $e->getMessage()]);
            }

            $productoEcommerce->categorias()->sync($validated['categorias'] ?? []);

            if ($request->hasFile('imagenes')) {
                foreach ($request->file('imagenes') as $index => $file) {
                    $path = $this->guardarImagenOptimizada($file);
                    Imagen::create([
                        'producto_id' => $validated['producto_id'],
                        'url' => $path,
                        'orden' => $index,
                        'principal' => $index === 0,
                    ]);
                }
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Producto publicado en la web');
    }

    public function edit(ProductoEcommerce $productoEcommerce)
    {
        $productoEcommerce->load(['producto.imagenes', 'categorias']);
 
        return Inertia::render('Admin/Products/Edit', [
            'productoEcommerce' => [
                'id' => $productoEcommerce->id,
                'slug' => $productoEcommerce->slug,
                'descripcion' => $productoEcommerce->descripcion,
                'precio_online' => $productoEcommerce->precio_online,
                'precio_oferta' => $productoEcommerce->precio_oferta,
                'stock_maximo_web' => $productoEcommerce->stock_maximo_web,
                'activo' => $productoEcommerce->activo,
                'destacado' => $productoEcommerce->destacado,
                'meta_title' => $productoEcommerce->meta_title,
                'meta_description' => $productoEcommerce->meta_description,
                'categorias' => $productoEcommerce->categorias,
                'grupo_variante_id' => $productoEcommerce->grupo_variante_id,
                'variante_tamano' => $productoEcommerce->variante_tamano,
                'variante_color_nombre' => $productoEcommerce->variante_color_nombre,
                'variante_color_hex' => $productoEcommerce->variante_color_hex,
                'producto' => [
                    'nombre' => $productoEcommerce->producto->nombre,
                    'codigo_barras' => $productoEcommerce->producto->codigo_barras,
                    'stock_pos' => $productoEcommerce->producto->stock_total,
                    'imagenes' => $productoEcommerce->producto->imagenes,
                ],
            ],
            'categorias' => CategoriaEcommerce::orderBy('orden')->get(),
            'gruposVariantes' => GrupoVariante::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, ProductoEcommerce $productoEcommerce)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo_barras' => 'nullable|string|unique:productos,codigo_barras,' . $productoEcommerce->producto_id,
            'descripcion' => 'nullable|string',
            'precio_online' => 'nullable|numeric|min:0',
            'precio_oferta' => 'nullable|numeric|min:0',
            'stock_maximo_web' => 'nullable|integer|min:0',
            'activo' => 'boolean',
            'destacado' => 'boolean',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'categorias' => 'array',
            'categorias.*' => 'exists:categorias_ecommerce,id',
            'imagenes.*' => 'nullable|image|max:4096',
            'imagenes_eliminar' => 'array',
            'imagenes_eliminar.*' => 'exists:imagenes,id',
            'grupo_variante_id' => 'nullable|exists:grupo_variantes,id',
            'nuevo_grupo_nombre' => 'nullable|string|max:100',
            'variante_tamano' => 'nullable|string|max:50',
            'variante_color_nombre' => 'nullable|string|max:50',
            'variante_color_hex' => 'nullable|string|max:7',
        ]);

        $this->validarPrecioOferta(
            $validated['precio_oferta'] ?? null,
            $validated['precio_online'] ?? null,
            (float) $productoEcommerce->producto->precio
        );

        $grupoVarianteId = $this->resolverGrupoVariante($validated);
        $this->validarDatosDeVariante($grupoVarianteId, $validated);

        DB::transaction(function () use ($validated, $request, $productoEcommerce, $grupoVarianteId) {
            $productoEcommerce->producto->update([
                'nombre' => $validated['nombre'],
                'codigo_barras' => $validated['codigo_barras'] ?: $productoEcommerce->producto->codigo_barras,
            ]);

            try {
                $productoEcommerce->update([
                    'descripcion' => $validated['descripcion'] ?? null,
                    'precio_online' => $validated['precio_online'] ?? null,
                    'precio_oferta' => $validated['precio_oferta'] ?? null,
                    'stock_maximo_web' => $validated['stock_maximo_web'] ?? null,
                    'activo' => $validated['activo'] ?? false,
                    'destacado' => $validated['destacado'] ?? false,
                    'meta_title' => $validated['meta_title'] ?? null,
                    'meta_description' => $validated['meta_description'] ?? null,
                    'grupo_variante_id' => $grupoVarianteId,
                    'variante_tamano' => $validated['variante_tamano'] ?? null,
                    'variante_color_nombre' => $validated['variante_color_nombre'] ?? null,
                    'variante_color_hex' => $validated['variante_color_hex'] ?? null,
                ]);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages(['grupo_variante_id' => $e->getMessage()]);
            }

            $productoEcommerce->categorias()->sync($validated['categorias'] ?? []);

            // Borrar imágenes marcadas — solo acá, dentro de la transacción de guardado
            if (!empty($validated['imagenes_eliminar'])) {
                $imagenes = Imagen::whereIn('id', $validated['imagenes_eliminar'])
                    ->where('producto_id', $productoEcommerce->producto_id) // seguridad: solo imágenes de este producto
                    ->get();
 
                foreach ($imagenes as $imagen) {
                    if (Storage::disk('public')->exists($imagen->url)) {
                        Storage::disk('public')->delete($imagen->url);
                    }
                    $imagen->delete();
                }
            }
 
            if ($request->hasFile('imagenes')) {
                $ordenBase = $productoEcommerce->producto->imagenes()->count();
                foreach ($request->file('imagenes') as $index => $file) {
                    $path = $this->guardarImagenOptimizada($file);
                    Imagen::create([
                        'producto_id' => $productoEcommerce->producto_id,
                        'url' => $path,
                        'orden' => $ordenBase + $index,
                        'principal' => $ordenBase === 0 && $index === 0,
                    ]);
                }
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Producto actualizado');
    }

    public function destroy(ProductoEcommerce $productoEcommerce)
    {
        // Solo borra la ficha web, el producto del POS queda intacto
        $productoEcommerce->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Producto retirado de la web');
    }

    public function buscarProductoPos(Request $request)
    {
        $q = $request->input('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $productos = Producto::whereDoesntHave('ecommerce')
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo_barras', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'nombre', 'codigo_barras']);

        return response()->json($productos);
    }

    // Slug automatico
    private function generarSlugUnico(string $nombre): string
    {
        $slugBase = Str::slug($nombre);
        $slug = $slugBase;
        $i = 1;

        while (ProductoEcommerce::where('slug', $slug)->exists()) {
            $i++;
            $slug = "{$slugBase}-{$i}";
        }

        return $slug;
    }

    /**
     * El precio de oferta tiene que ser estrictamente menor al precio de
     * lista real (precio_online si está cargado, si no el precio base del
     * producto en el POS). Si no, el "descuento" no seria un descuento.
     */
    private function validarPrecioOferta(?float $precioOferta, ?float $precioOnline, float $precioBase): void
    {
        if ($precioOferta === null) {
            return;
        }

        $precioLista = $precioOnline ?? $precioBase;

        if ($precioOferta >= $precioLista) {
            throw ValidationException::withMessages([
                'precio_oferta' => 'El precio de oferta debe ser menor al precio de lista ($'
                    . number_format($precioLista, 2, ',', '.') . ').',
            ]);
        }
    }

    /**
     * Resuelve a qué grupo de variantes queda asignado el producto: si el
     * admin escribió un nombre para un grupo nuevo, lo crea y usa ese id;
     * si no, usa el id de un grupo existente que haya elegido (o null si
     * no eligió ninguno — producto normal, sin variantes).
     */
    private function resolverGrupoVariante(array $validated): ?int
    {
        if (!empty($validated['nuevo_grupo_nombre'])) {
            return GrupoVariante::create(['nombre' => $validated['nuevo_grupo_nombre']])->id;
        }

        return $validated['grupo_variante_id'] ?? null;
    }

    /**
     * Si el producto queda asignado a un grupo, tiene que distinguirse de
     * sus hermanos por algo — no tiene sentido un grupo donde ningún
     * producto tenga tamaño ni color cargado.
     */
    private function validarDatosDeVariante(?int $grupoVarianteId, array $validated): void
    {
        if ($grupoVarianteId === null) {
            return;
        }

        if (empty($validated['variante_tamano']) && empty($validated['variante_color_nombre'])) {
            throw ValidationException::withMessages([
                'grupo_variante_id' => 'Si asignás un grupo de variantes, cargá al menos el tamaño o el color.',
            ]);
        }
    }

    private function guardarImagenOptimizada($file): string
    {
        $encoded = Image::decode($file)
            ->scale(width: 1200)
            ->encodeUsingFormat(Format::WEBP, quality: 80);

        $filename = 'productos/' . uniqid() . '.webp';
        Storage::disk('public')->put($filename, (string) $encoded);

        return $filename;
    }
}
