<?php

namespace App\Livewire\Admin\Productos;

use Livewire\Component;

class FiltrarProductos extends Component
{
    public $termino;
    public $stock;
    public $fecha;
    public $sinVencimientos = false;
    public $eventName = 'terminosBusqueda';
    public $showTermino = false;
    public $showStockOrder = false;
    public $showVencimiento= false;

    public function leerDatosFormulario()
    {
        $payload = [
            'termino' => $this->termino !== '' ? trim($this->termino) : null,
            'stock' => ($this->stock !== '' && is_numeric($this->stock)) ? intval($this->stock) : null,
            'sinVencimientos' => (bool) $this->sinVencimientos,
            'fecha' => $this->fecha ?: null,
        ];

        // solo agregar fecha si el control está visible y hay valor
        if ($this->showVencimiento && $this->fecha) {
            // fecha viene en formato YYYY-MM-DD desde el input type="date"
            $payload['fecha'] = $this->fecha;
        }

        $this->dispatch($this->eventName, $payload);
    }

    public function render()
    {
        return view('livewire.admin.productos.filtrar-productos');
    }
}
