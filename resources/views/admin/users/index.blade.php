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

            <a href="{{ route('admin.users.create') }}" class="bg-green-500 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-green-700">>
                Crear Nuevo Administrador
            </a>

            <livewire:mostrar-administradores />
        </div>
    </div>

    
</x-app-layout>