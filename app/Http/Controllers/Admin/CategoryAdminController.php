<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaEcommerce;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CategoryAdminController extends Controller
{
    public function index()
    {
        $categorias = CategoriaEcommerce::withCount('productos')
            ->orderBy('orden')
            ->get();

        return Inertia::render('Admin/Categories/Index', [
            'categorias' => CategoriaEcommerce::construirArbol($categorias),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Admin/Categories/Create', [
            'categoriasPadre' => CategoriaEcommerce::orderBy('nombre')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->rutaCompleta()])
                ->values(),
            // Si venimos del botón "+ Subcategoría" del árbol, precargamos el padre.
            'categoriaPadreIdPreseleccionada' => $request->integer('parent') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'slug' => 'nullable|string|unique:categorias_ecommerce,slug',
            'categoria_padre_id' => 'nullable|exists:categorias_ecommerce,id',
            'orden' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['nombre']);
        $validated['orden'] = $validated['orden'] ?? 0;

        if (!empty($validated['categoria_padre_id'])) {
            $padre = CategoriaEcommerce::find($validated['categoria_padre_id']);

            if ($padre->profundidad() + 1 >= CategoriaEcommerce::MAX_PROFUNDIDAD) {
                return back()->withErrors([
                    'categoria_padre_id' => 'Se alcanzó la profundidad máxima de '
                        . CategoriaEcommerce::MAX_PROFUNDIDAD . ' niveles para esa rama.',
                ])->withInput();
            }
        }

        CategoriaEcommerce::create($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría creada correctamente');
    }

    public function edit(CategoriaEcommerce $category)
    {
        $excluidos = array_merge([$category->id], $category->todosLosDescendientesIds());

        return Inertia::render('Admin/Categories/Edit', [
            'categoria' => $category,
            'categoriasPadre' => CategoriaEcommerce::whereNotIn('id', $excluidos)
                ->orderBy('nombre')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->rutaCompleta()])
                ->values(),
        ]);
    }

    public function update(Request $request, CategoriaEcommerce $category)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'slug' => 'required|string|unique:categorias_ecommerce,slug,' . $category->id,
            'categoria_padre_id' => 'nullable|exists:categorias_ecommerce,id',
            'orden' => 'nullable|integer|min:0',
        ]);

        if (!empty($validated['categoria_padre_id'])) {
            if ((int) $validated['categoria_padre_id'] === $category->id) {
                return back()->withErrors([
                    'categoria_padre_id' => 'Una categoría no puede ser su propia categoría padre.',
                ])->withInput();
            }

            $descendientesIds = $category->todosLosDescendientesIds();

            if (in_array((int) $validated['categoria_padre_id'], $descendientesIds, true)) {
                return back()->withErrors([
                    'categoria_padre_id' => 'No podés mover esta categoría dentro de una de sus propias subcategorías.',
                ])->withInput();
            }

            $padre = CategoriaEcommerce::find($validated['categoria_padre_id']);

            if ($padre->profundidad() + 1 >= CategoriaEcommerce::MAX_PROFUNDIDAD) {
                return back()->withErrors([
                    'categoria_padre_id' => 'Se alcanzó la profundidad máxima de '
                        . CategoriaEcommerce::MAX_PROFUNDIDAD . ' niveles para esa rama.',
                ])->withInput();
            }
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría actualizada');
    }

    public function destroy(CategoriaEcommerce $category)
    {
        if ($category->subcategorias()->exists()) {
            return back()->with('error',
                'No podés eliminar una categoría que tiene subcategorías. Eliminá o reasigná las subcategorías primero.'
            );
        }

        if ($category->productos()->exists()) {
            return back()->with('error',
                'No podés eliminar una categoría que tiene productos asignados.'
            );
        }

        $category->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Categoría eliminada');
    }
}
