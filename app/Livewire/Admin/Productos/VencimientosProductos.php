<?php

namespace App\Livewire\Admin\Productos;

use Livewire\Component;
use App\Models\Producto;
use Livewire\WithPagination;

class VencimientosProductos extends Component
{
    use WithPagination;

    public $fecha;
    public $search;
    public $stockMinimo = null;
    public $sinVencimientos = false;

    protected $listeners = ['terminosBusqueda' => 'buscar'];

    public function mount()
    {
        $this->fecha = null;
        $this->search = null;
    }

    public function buscar($filtros = null)
    {
        if( is_array($filtros)){
            $this->fecha = $filtros['fecha'] ?? null;

            $this->search = !empty($filtros['termino']) ? trim($filtros['termino']) : null;

            $this->stockMinimo = (isset($filtros['stock']) && $filtros['stock'] !== '' && is_numeric($filtros['stock']))
                ? intval($filtros['stock'])
                : null;

            $this->sinVencimientos = !empty($filtros['sinVencimientos']);
        } elseif (is_string($filtros)) {
            // Si llega un string, asumimos búsqueda por texto (y no tocamos fecha)
            $this->search = $filtros !== '' ? trim($filtros) : null;
        } else {
            $this->reset(['fecha','search','stockMinimo','sinVencimientos']);
        }

        $this->resetPage();
    }

    public function render()
    {
        $query = Producto::with([
                'vencimientos' => function ($q) {
                    $q->orderBy('fecha_vencimiento', 'asc');
                },
                'ofertas'
            ])
            ->withSum('vencimientos', 'cantidad');

        // búsqueda por nombre o código
        $query->when($this->search, function($q) {
            $q->where(function($q2) {
                $q2->where('codigo_barras', 'like', "%{$this->search}%")
                ->orWhere('nombre', 'like', "%{$this->search}%");
            });
        });

        // si piden "sin vencimientos"
        if ($this->sinVencimientos) {
            $query->doesntHave('vencimientos');
        } else {
            $query->has('vencimientos');
            
            // filtrar por mes/año exacto (YYYY-MM) si se pasó
            if (!empty($this->fecha) && preg_match('/^\d{4}-\d{2}$/', $this->fecha)) {
                // como tu campo es VARCHAR('YYYY-MM'), usamos igualdad
                $query->whereHas('vencimientos', fn($q) => $q->where('fecha_vencimiento', $this->fecha));
            }
        }

        // si se pide stock mínimo (opcional)
        if ($this->stockMinimo !== null) {
            $query->having('vencimientos_sum_cantidad', '>=', $this->stockMinimo);
        }

        // orden por suma de vencimientos (puedes cambiar si preferís otro orden)
        $query->orderBy('vencimientos_sum_cantidad', 'asc');

        $productos = $query->paginate(10);

        return view('livewire.admin.productos.vencimientos-productos', compact('productos'));
    }
}
