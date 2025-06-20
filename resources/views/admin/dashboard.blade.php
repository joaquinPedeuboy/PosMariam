<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Panel de Administración') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-semibold">¡Hola {{ auth()->user()->name }}!</h3>
            <p class="mt-2">Desde aquí podés administrar el sistema.</p>

            <div class="mt-6">
                <a href="{{ route('admin.users.index') }}"
                    class="text-indigo-600 hover:text-indigo-800 underline">
                    👉 Gestionar Usuarios
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
