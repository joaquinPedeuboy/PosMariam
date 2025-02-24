<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'codigo_barras' => 'required|string|unique:productos,codigo_barras',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'vencimientos' => 'required|array',
            'vencimientos.*.fecha_vencimiento' => 'required|date_format:Y-m', // Validación YYYY-MM
            'vencimientos.*.cantidad' => 'required|integer|min:0',
            'oferta' => 'nullable|array',
            'oferta.*.cantidad' => 'nullable|integer|min:0', 
            'oferta.*.precio_oferta' => [
                'nullable','numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    if (request()->precio && $value >= request()->precio) {
                        $fail('El precio de oferta no puede ser igual o mayor al precio del producto.');
                    }
                }
            ],

        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre del producto es obligatorio.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un número.',
            'codigo_barras.required' => 'El código de barras es obligatorio.',
            'codigo_barras.unique' => 'El código de barras ya está registrado.',
            'departamento_id.exists' => 'El departamento seleccionado no es válido.',
            'vencimientos.required' => 'Debe agregar al menos un vencimiento.',
            'vencimientos.*.fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'vencimientos.*.cantidad.required' => 'La cantidad es obligatoria.',
            'vencimientos.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'vencimientos.*.cantidad.min' => 'La cantidad no puede ser negativa.',

            'oferta.*.precio_oferta.min' => 'El precio de oferta no puede ser negativo.',
        ];
    }
}
