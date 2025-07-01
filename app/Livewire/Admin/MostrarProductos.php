<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Producto;
use Livewire\WithPagination;

class MostrarProductos extends Component
{
    use WithPagination;

    public function render()
    {
        $productos = Producto::with(['vencimientos', 'ofertas'])->paginate(10);

        return view('livewire.admin.mostrar-productos', compact('productos'));
    }
}
