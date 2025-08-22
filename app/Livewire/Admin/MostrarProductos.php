<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Producto;
use Livewire\WithPagination;

class MostrarProductos extends Component
{
    use WithPagination;

    public $search;

    protected $listeners = ['terminosBusqueda' => 'buscar'];

    public function buscar($filtros = [])
    {
        if (is_array($filtros) && isset($filtros['termino'])) {
            $this->search = $filtros['termino'];
        } elseif (is_string($filtros)) {
            $this->search = $filtros;
        }
        $this->resetPage();
    }

    public function render()
    {
        $productos = Producto::with(['vencimientos', 'ofertas'])
            ->when($this->search && is_string($this->search), function($query) {
                $query->where(function($q) {
                    $q->where('codigo_barras', 'LIKE', '%' . $this->search . '%')
                    ->orWhere('nombre', 'LIKE', '%' . $this->search . '%');
                });
            })
            ->paginate(10);

        return view('livewire.admin.mostrar-productos', compact('productos'));
    }
}
