<?php

namespace App\Services;

use App\Models\ShippingSetting;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class ShippingCostService
{
    public function calcular(
        string $metodo,
        float $subtotal,
        ?string $ciudad = null,
        ?string $provincia = null
    ): array {
        if ($metodo === 'retiro_local') {
            return [
                'metodo_entrega' => 'retiro_local',
                'costo_envio' => 0,
                'nombre_metodo' => 'Retiro en local',
            ];
        }

        if ($metodo === 'motomensajeria') {
            $this->validarZonaMotomensajeria($ciudad, $provincia);

            $configuracion = ShippingSetting::actual();

            if (! $configuracion->motomensajeria_habilitada) {
                throw ValidationException::withMessages([
                    'metodo_entrega' => 'La motomensajería no está disponible actualmente.',
                ]);
            }

            $costo = $subtotal >= (float) $configuracion->motomensajeria_minimo_gratis
                ? 0
                : (float) $configuracion->motomensajeria_tarifa;

            return [
                'metodo_entrega' => 'motomensajeria',
                'costo_envio' => $costo,
                'nombre_metodo' => 'Motomensajería',
            ];
        }

        throw ValidationException::withMessages([
            'metodo_entrega' => 'El método de entrega seleccionado no es válido.',
        ]);
    }

    private function validarZonaMotomensajeria(
        ?string $ciudad,
        ?string $provincia
    ): void {
        $ciudadNormalizada = Str::lower(
            Str::ascii(trim($ciudad ?? ''))
        );

        $provinciaNormalizada = Str::lower(
            Str::ascii(trim($provincia ?? ''))
        );

        if (
            $ciudadNormalizada !== 'mercedes'
            || ! in_array($provinciaNormalizada, [
                'buenos aires',
                'provincia de buenos aires',
            ], true)
        ) {
            throw ValidationException::withMessages([
                'metodo_entrega' => 'La motomensajería está disponible únicamente en Mercedes, Buenos Aires.',
            ]);
        }
    }
}