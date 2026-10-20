<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerAddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('es_predeterminada')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'addresses' => $addresses,
        ]);
    }

    /**
     * Crear una dirección.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo_domicilio' => [
                'required',
                Rule::in(['casa', 'departamento', 'otro']),
            ],
            'calle' => ['required', 'string', 'max:150'],
            'numero' => ['required', 'string', 'max:30'],
            'piso' => ['nullable', 'string', 'max:20'],
            'departamento' => ['nullable', 'string', 'max:30'],
            'torre_edificio' => ['nullable', 'string', 'max:100'],
            'informacion_adicional' => ['nullable', 'string', 'max:2000'],
            'referencia_envio' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['required', 'string', 'max:100'],
            'provincia' => ['required', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'nombre_contacto' => ['required', 'string', 'max:150'],
            'telefono_contacto' => ['required', 'string', 'max:30'],
            'es_predeterminada' => ['sometimes', 'boolean'],
        ]);

        $customer = $request->user();

        $address = DB::transaction(function () use ($customer, $validated) {
            $esPredeterminada = (bool) ($validated['es_predeterminada'] ?? false);

            // La primera dirección del cliente será la predeterminada.
            if (! $customer->addresses()->exists()) {
                $esPredeterminada = true;
            }

            if ($esPredeterminada) {
                $customer->addresses()->update([
                    'es_predeterminada' => false,
                ]);
            }

            $validated['es_predeterminada'] = $esPredeterminada;

            return $customer->addresses()->create($validated);
        });

        return response()->json([
            'message' => 'Dirección creada correctamente.',
            'address' => $address,
        ], 201);
    }

    /**
     * Actualizar una dirección propia.
     */
    public function update(Request $request, int $id)
    {
        $customer = $request->user();

        // Solo permite encontrar direcciones pertenecientes al cliente.
        $address = $customer->addresses()->findOrFail($id);

        $validated = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'tipo_domicilio' => [
                'sometimes',
                'required',
                Rule::in(['casa', 'departamento', 'otro']),
            ],
            'calle' => ['sometimes', 'required', 'string', 'max:150'],
            'numero' => ['sometimes', 'required', 'string', 'max:30'],
            'piso' => ['sometimes', 'nullable', 'string', 'max:20'],
            'departamento' => ['sometimes', 'nullable', 'string', 'max:30'],
            'torre_edificio' => ['sometimes', 'nullable', 'string', 'max:100'],
            'informacion_adicional' => [
                'sometimes', 'nullable', 'string', 'max:2000',
            ],
            'referencia_envio' => [
                'sometimes', 'nullable', 'string', 'max:255',
            ],
            'ciudad' => ['sometimes', 'required', 'string', 'max:100'],
            'provincia' => ['sometimes', 'required', 'string', 'max:100'],
            'codigo_postal' => [
                'sometimes', 'nullable', 'string', 'max:20',
            ],
            'nombre_contacto' => [
                'sometimes', 'required', 'string', 'max:150',
            ],
            'telefono_contacto' => [
                'sometimes', 'required', 'string', 'max:30',
            ],
            'es_predeterminada' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($customer, $address, $validated) {
            if (($validated['es_predeterminada'] ?? false) === true) {
                $customer->addresses()
                    ->whereKeyNot($address->id)
                    ->update(['es_predeterminada' => false]);
            }

            $address->update($validated);
        });

        return response()->json([
            'message' => 'Dirección actualizada correctamente.',
            'address' => $address->fresh(),
        ]);
    }

    /**
     * Eliminar una dirección propia.
     */
    public function destroy(Request $request, int $id)
    {
        $customer = $request->user();

        $address = $customer->addresses()->findOrFail($id);
        $eraPredeterminada = $address->es_predeterminada;

        DB::transaction(function () use (
            $customer,
            $address,
            $eraPredeterminada
        ) {
            $address->delete();

            // Si se elimina la predeterminada, elegir otra automáticamente.
            if ($eraPredeterminada) {
                $siguiente = $customer->addresses()
                    ->orderBy('id')
                    ->first();

                if ($siguiente) {
                    $siguiente->update([
                        'es_predeterminada' => true,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Dirección eliminada correctamente.',
        ]);
    }
}
