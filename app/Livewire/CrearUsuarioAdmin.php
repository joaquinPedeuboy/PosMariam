<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class CrearUsuarioAdmin extends Component
{
    public $name;
    public $surname;
    public $email;
    public $password;
    public $password_confirmation;
    public $admin = true;

    protected $rules = [
        'name'     => 'required|string|max:255',
        'surname'  => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email|lowercase|max:255',
        'password' => 'required|min:6|confirmed',
        'password_confirmation' => 'required|min:6|same:password',
    ];

    
    protected function messages()
    {
        return [
            'password.confirmed'              => 'Las contraseñas no coinciden.',
            'password_confirmation.required'  => 'Debes confirmar la contraseña.',
            'password_confirmation.same'      => 'Las contraseñas no coinciden.',
        ];
    }

    public function crearAdministrador()
    {
        $this->validate();

        User::create([
            'name'     => $this->name,
            'surname'  => $this->surname,
            'email'    => $this->email,
            'password' => Hash::make($this->password),
            'admin' => $this->admin,
        ]);

        // Crear un mensaje
        session()->flash('mensaje', 'Administrador creado correctamente');

        // Redireccionar al usuario
        return redirect()->route('admin.users.index');
    }


    public function render()
    {
        return view('livewire.crear-usuario-admin');
    }
}
