<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Panel de Administración') }}
        </h2>
    </x-slot>

    <div class="py-12 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h3 class="text-xl font-bold mx-4">¡Hola {{ auth()->user()->name }}!</h3>
        <p class="mt-2 mb-4 mx-4">Desde aquí podés administrar el sistema.</p>
        <div class="bg-white shadow-sm p-4 m-4 md:flex md:justify-evenly md:items-center sm:rounded-lg">

            {{-- Seccion Administradores --}}
            <div class="flex flex-col items-stretch mb-4">
                <h5 class="text-base font-semibold mb-2 text-center">Seccion Administradores</h5>
                <a href="{{ route('admin.users.index') }}"
                    class="bg-green-500 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-green-700">
                    👉 Gestionar Admins
                </a>
            </div>

            {{-- Seccion Productos --}}
            <div class="flex flex-col items-stretch mb-4">
                <h5 class="text-base font-semibold mb-2 text-center">Seccion Productos</h5>
                <a href="{{ route('admin.productos.index') }}"
                    class="bg-purple-500 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-purple-700">
                    👉 Gestionar Productos
                </a>
            </div>

            {{-- Seccion Ventas --}}
            <div class="flex flex-col items-stretch mb-4">
                <h5 class="text-base font-semibold mb-2 text-center">Seccion Ventas</h5>
                <a href="{{ route('admin.productos.index') }}"
                    class="bg-yellow-500 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-yellow-700">
                    👉 Verificar Ventas
                </a>
            </div>

            {{-- Seccion Vencimientos --}}
            <div class="flex flex-col items-stretch mb-4">
                <h5 class="text-base font-semibold mb-2 text-center">Seccion Vencimientos</h5>
                <a href="{{ route('admin.productos.index') }}"
                    class="bg-red-600 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-red-700">
                    👉 Verificar Vencimientos de Productos
                </a>
            </div>
        </div>
    </div>

    <div>
        
    </div>
</x-app-layout>
