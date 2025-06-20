<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class EditarUsuarioAdmin extends Component
{
    public $user_id;
    public $name;
    public $surname;
    public $email;
    public $password;
    public $password_confirmation;
    public $admin = true;

    public function rules()
    {
        return [
            'name'     => 'required|string|max:255',
            'surname'  => 'required|string|max:255',
            'email'    => 'required|email|lowercase|max:255|unique:users,email,' . $this->user_id,
            'password' => 'nullable|min:6|confirmed',
            'password_confirmation' => 'nullable|min:6|same:password',
        ];
    }


    protected function messages()
    {
        return [
            'password.confirmed'              => 'Las contraseñas no coinciden.',
            'password_confirmation.same'      => 'Las contraseñas no coinciden.',
        ];
    }

    public function mount(User $user)
    {
        $this->user_id = $user->id;
        $this->name = $user->name;
        $this->surname = $user->surname;
        $this->email = $user->email;
    }

    public function editarAdministrador()
    {
        $datos = $this->validate();

        //Encontrar al usuario a editar
        $user = User::find($this->user_id);

        //Asignar los valores
        $user->name = $datos['name'];
        $user->surname = $datos['surname'];
        $user->email = $datos['email'];
        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        // guardar el usuario
        $user->save();

        // Redirrecionar
        session()->flash('mensaje', 'El usuario fue actualizado correctamente');

        return redirect()->route('admin.users.index');

    }

    public function render()
    {
        return view('livewire.editar-usuario-admin');
    }
}
