<?php

namespace App\Livewire\Admin\Productos;

use Livewire\Component;
use App\Models\Producto;
use App\Models\Departamento;
use Illuminate\Support\Facades\DB;

class CrearProducto extends Component
{
    public $nombre;
    public $codigo_barras;
    public $precio;
    public $departamento_id;
    public $disponible = true;

    public $vencimientos = [['fecha_vencimiento' => '', 'cantidad' => '']];
    public $oferta = ['precio_oferta' => '', 'cantidad' => ''];

    public function agregarVencimiento()
    {
        $this->vencimientos[] = ['fecha_vencimiento' => '', 'cantidad' => ''];
    }

    public function eliminarVencimiento($index)
    {
        unset($this->vencimientos[$index]);
        $this->vencimientos = array_values($this->vencimientos);
    }

    public function guardar()
    {
        $this->validate([
            'nombre' => 'required|string|max:255',
            'precio' => 'required|numeric|min:0',
            'codigo_barras' => 'required|string|unique:productos,codigo_barras',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'disponible' => 'nullable|boolean',
            'vencimientos' => 'required|array',
            'vencimientos.*.fecha_vencimiento' => 'required|date_format:Y-m',
            'vencimientos.*.cantidad' => 'required|integer|min:0',
            'oferta' => 'nullable|array',
            'oferta.cantidad' => 'nullable|integer|min:0|required_with:oferta.precio_oferta',
            'oferta.precio_oferta' => [
                'nullable', 'numeric', 'min:0', 'required_with:oferta.cantidad',
                function ($attribute, $value, $fail) {
                    if ($value && $this->precio && $value >= $this->precio) {
                        $fail('El precio de oferta no puede ser igual o mayor al precio del producto.');
                    }
                }
            ],
        ], [
            'vencimientos.*.fecha_vencimiento.required' => 'La fecha de vencimiento es obligatoria.',
            'vencimientos.*.fecha_vencimiento.date_format' => 'El formato debe ser año-mes (YYYY-MM).',
            'vencimientos.*.cantidad.required' => 'La cantidad es obligatoria.',
            'vencimientos.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'vencimientos.*.cantidad.min' => 'La cantidad debe ser al menos 0.',
            'oferta.precio_oferta.required_with' => 'El precio de oferta es obligatorio si hay cantidad.',
            'oferta.cantidad.required_with' => 'La cantidad es obligatoria si hay precio de oferta.',
        ]);

        DB::beginTransaction();

        try {
            $producto = Producto::create([
                'nombre' => $this->nombre,
                'precio' => $this->precio,
                'codigo_barras' => $this->codigo_barras,
                'departamento_id' => $this->departamento_id,
                'disponible' => $this->disponible ? true : false,
            ]);

            foreach ($this->vencimientos as $v) {
                $producto->vencimientos()->create($v);
            }

            if ($this->oferta['precio_oferta'] && $this->oferta['cantidad']) {
                $producto->ofertas()->create($this->oferta);
            }

            DB::commit();

            // Crear un mensaje
            session()->flash('mensaje', 'Producto creado correctamente ✅');
            return redirect()->route('admin.productos.index');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('general', 'Error al crear el producto: ' . $e->getMessage());
        }
            
    }

    public function render()
    {
        return view('livewire.admin.productos.crear-producto', [
            'departamentos' => Departamento::all(),
        ]);
    }
}
