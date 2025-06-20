<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class MostrarAdministradores extends Component
{

    use WithPagination;

    public function eliminar($id)
    {
        // Evitar que se elimine a sí mismo
        if (auth()->id() === $id) {
            session()->flash('mensaje', 'No puedes eliminar tu propio usuario.');
            return;
        }

        $user = User::findOrFail($id);
        $user->delete();

        session()->flash('mensaje', 'Administrador eliminado correctamente');
    }
    
    public function render()
    {
        $users = User::where('admin', true)->paginate(10);

        return view('livewire.mostrar-administradores', [
            'users' => $users
        ]);
    }
}
