<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Administradores') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Mensaje de alerta --}}
            @if (session()->has('mensaje'))
                    <div class="uppercase border border-green-600 bg-green-100 text-green-600 font-bold p-2 my-3 text-sm">
                        {{ session('mensaje') }}
                    </div>
            @endif

        </div>
        <a href="{{ route('admin.users.create') }}">Crear Usuario</a>

        <ul>
            @foreach($users as $user)
                <li>{{ $user->name }} ({{ $user->email }}) - 
                    <a href="{{ route('admin.users.edit', $user) }}">Editar</a> | 
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" style="display:inline">
                        @csrf @method('DELETE')
                        <button onclick="return confirm('¿Eliminar usuario?')">Eliminar</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>

    
</x-app-layout>