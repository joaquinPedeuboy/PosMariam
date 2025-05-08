<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\DepartamentoRequest;

class DepartamentoController extends Controller
{
    public function index(Request $request)
    {
        $query = Departamento::query();

        if ($busqueda = $request->input('busqueda')) {
            $query->where('nombre', 'like', '%' . $busqueda . '%');
        }

        $perPage = $request->input('per_page', 12);
        $departamentos = $query->paginate($perPage);

        return response()->json($departamentos);
    }

    public function store(DepartamentoRequest $request)
    {
        try {
            $data = $request->validated();
    
            $departamento = Departamento::create($request->validated());
    
            return response()->json([
                'departamento' => $departamento,  // Aquí devolvemos el departamento con su ID
                'message' => 'Departamento creado con éxito'
            ]);
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'error' => 'Hubo un error al crear el departamento'], 500);
        }
        
    }

    public function update(DepartamentoRequest $request, Departamento $departamento)
    {
        DB::beginTransaction();
        try {
             // Obtener los datos validados del request
            $validated = $request->validated();

            // Actualizar los detalles del producto
            $departamento->update([
                'nombre' => $validated['nombre'] ?? $departamento->nombre,
            ]);

            DB::commit();
            return response()->json([
                'departamento' => $departamento,
                'message' => 'Departamento actualizado correctamente',
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el departamento',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function destroy(Departamento $departamento)
    {
        try {
            $departamento->delete();
            return response()->json([
                'success' => true,
                'message' => 'Departamento eliminado con éxito',
                'departamento_id' => $departamento->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Hubo un error al eliminar el departamento'], 500);
        }
    }

    // Endpoint para obtener todos los departamentos (sin paginación)
    public function all()
    {
        $departamentos = Departamento::all();
        return response()->json($departamentos);
    }

}
