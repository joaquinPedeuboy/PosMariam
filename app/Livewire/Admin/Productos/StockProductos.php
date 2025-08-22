<?php

namespace App\Livewire\Admin\Productos;

use Livewire\Component;
use App\Models\Producto;
use Livewire\WithPagination;

class StockProductos extends Component
{
    use WithPagination;

    public $search;
    public $productosFiltrados = [];
    public $stockMinimo = null;
    public $sinVencimientos = false;


    protected $listeners = ['terminosBusqueda' => 'buscar'];

    public function buscar($filtros = null)
    {
        if(is_string($filtros)){
            $this->search = $filtros !== '' ? trim($filtros) : null;
            $this->stockMinimo = null;
            $this->sinVencimientos = false;
        } elseif (is_array($filtros)) {
            $this->search = !empty($filtros['termino']) ? trim($filtros['termino']) : null;
            $this->stockMinimo = (isset($filtros['stock']) && $filtros['stock'] !== '' && is_numeric($filtros['stock']))
                                ? intval($filtros['stock'])
                                : null;
            $this->sinVencimientos = !empty($filtros['sinVencimientos']);
        } else {
            // null u otro: limpiar todo
            $this->search = null;
            $this->stockMinimo = null;
            $this->sinVencimientos = false;
            
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Producto::with(['vencimientos', 'ofertas'])
        // Esto añade un atributo virtual `vencimientos_sum_cantidad`
        ->withSum('vencimientos', 'cantidad');

         // término (nombre o código)
        $query->when($this->search, function($q) {
            $q->where(function($q2) {
                $q2->where('codigo_barras', 'like', "%{$this->search}%")
                ->orWhere('nombre', 'like', "%{$this->search}%");
            });
        });

        // Filtro por stock mínimo (sumatoria de vencimientos)
        if ($this->stockMinimo !== null) {
            // `having` funciona porque `withSum` añade un GROUP BY internamente
            // ver productos con "al menos" stockMinimo -> >=
            $query->having('vencimientos_sum_cantidad', '<=', $this->stockMinimo);
        }

        // filtro “Sin vencimientos”
        if ($this->sinVencimientos) {
            $query->doesntHave('vencimientos');
        }

        // Ordenamos por la suma de vencimientos (de menor a mayor)
        $query->orderBy('vencimientos_sum_cantidad', 'asc');

        // Paginamos en SQL
        $productos = $query->paginate(10);

        return view('livewire.admin.productos.stock-productos', compact('productos'));
    }
}
