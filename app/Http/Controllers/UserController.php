<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    //Controlador para Admins
    public function index()
    {
        $users = User::all();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'surname'  => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email|lowercase|max:255',
            'password' => 'required|min:6|confirmed',
            'admin'    => 'required|boolean',
        ]);

        User::create([
            'name'     => $request->name,
            'surname'  => $request->surname,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'admin'    => $request->admin,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario creado con éxito');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'surname'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id . '|lowercase|max:255',
            'password' => 'nullable|min:6|confirmed',
            'admin'    => 'required|boolean',
        ]);

        $user->update([
            'name'     => $request->name,
            'surname'  => $request->surname,
            'email'    => $request->email,
            'admin'    => $request->admin,
            'password' => $request->password ? Hash::make($request->password) : $user->password,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Usuario actualizado');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success', 'Usuario eliminado');
    }
}
