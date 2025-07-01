<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;

class MostrarAdministradores extends Component
{

    use WithPagination;

    #[On('eliminarAdministrador')]
    public function eliminarAdministrador($userId)
    {
        $id = (int) $userId;
        // Evitar que se elimine a sí mismo
        if (auth()->id() === $id) {
            session()->flash('mensaje', 'No puedes eliminar tu propio usuario.');
            return;
        }

        // Buscar solo administradores
        $user = User::where('id', $id)->where('admin', true)->firstOrFail();
        $user->delete();
    }
    
    public function render()
    {
        $users = User::where('admin', true)->paginate(10);

        return view('livewire.mostrar-administradores', [
            'users' => $users
        ]);
    }
}
