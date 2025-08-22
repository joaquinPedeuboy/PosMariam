<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-row justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Stock de Productos') }}
            </h2>

            <a 
                href="{{ route('admin.productos.create') }}" 
                class="bg-green-500 text-white px-4 py-3 rounded-lg text-xs font-bold uppercase text-center hover:bg-green-700"
            >
                Crear Nuevo Producto
            </a>
        </div>
        
    </x-slot>

    {{-- Mensaje de alerta --}}
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        
        @if (session()->has('mensaje'))
                <div class="uppercase border border-green-600 bg-green-100 text-green-600 font-bold p-2 my-3 text-sm">
                    {{ session('mensaje') }}
                </div>
        @endif
    </div>
    

    <div class="py-8">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <livewire:admin.productos.stock-productos />
        </div>
    </div>
</x-app-layout>