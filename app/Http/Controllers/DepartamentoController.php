<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use Illuminate\Http\Request;
use App\Http\Requests\DepartamentoRequest;

class DepartamentoController extends Controller
{
    public function index()
    {
        // Devuelve todos los departamentos
        $departamentos = Departamento::all();

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
}
