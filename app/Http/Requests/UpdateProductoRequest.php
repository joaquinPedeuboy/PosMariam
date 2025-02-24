<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductoRequest extends FormRequest
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
        $productoId = $this->route('producto')->id ?? null;

        return [
            'nombre' => 'nullable|string|max:255|required_if:nombre,null',
            'precio' => 'nullable|numeric|min:0|required_if:precio,null',
            'codigo_barras' => 'nullable|string|unique:productos,codigo_barras,' . $productoId . '|required_if:codigo_barras,null',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'vencimientos' => 'sometimes|array',
            'vencimientos.*.fecha_vencimiento' => 'required_with:vencimientos|date_format:Y-m',
            'vencimientos.*.cantidad' => 'required_with:vencimientos|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'nombre.required_if' => 'El nombre del producto no puede estar vacío.',
            'precio.required_if' => 'El precio no puede estar vacío.',
            'codigo_barras.required_if' => 'El código de barras no puede estar vacío.',
            'precio.numeric' => 'El precio debe ser un número.',
            'codigo_barras.unique' => 'El código de barras ya está registrado.',
            'departamento_id.exists' => 'El departamento seleccionado no es válido.',
            'vencimientos.*.fecha_vencimiento.required_with' => 'La fecha de vencimiento es obligatoria si hay vencimientos.',
            'vencimientos.*.cantidad.required_with' => 'La cantidad es obligatoria si hay vencimientos.',
            'vencimientos.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'vencimientos.*.cantidad.min' => 'La cantidad no puede ser negativa.',
        ];
    }
}
