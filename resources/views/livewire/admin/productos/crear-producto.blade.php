<form wire:submit.prevent="guardar" class="md:w-1/2 space-y-5">

    {{-- Nombre --}}
    <div>
        <x-input-label for="nombre" :value="__('Nombre')"/>
        <x-text-input
            id="nombre" 
            type="text"
            name="nombre"
            wire:model.live="nombre"
            class="block mt-1 w-full"
            :value="old('nombre')" 
            placeholder="Nombre Producto"
        />

        @error('nombre')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    
    </div>

    {{-- Precio --}}
    <div>
        <x-input-label for="precio" :value="__('Precio')"/>
        <x-text-input
            id="precio" 
            type="number"
            name="precio"
            wire:model.live="precio"
            class="sin-flechas block mt-1 w-full"
            :value="old('precio')" 
            placeholder="Precio Producto"
        />

        @error('precio')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    
    </div>

    {{-- Código de barras --}}
    <div>
        <x-input-label for="codigo_barras" :value="__('Código de Barras')"/>
        <x-text-input
            id="codigo_barras" 
            type="text"
            name="codigo_barras"
            wire:model.live="codigo_barras"
            class="block mt-1 w-full"
            :value="old('codigo_barras')" 
            placeholder="Escanea o ingresa el código de barras"
        />

        @error('codigo_barras')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    
    </div>

    {{-- Departamento --}}
    <div>
        <x-input-label for="departamento_id" :value="__('Departamento')" />
        <select 
            wire:model.live="departamento_id" 
            id="departamento_id"
            class="rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 w-full">
        >
            <option>-- Seleccione un Departamento --</option>
            @foreach (\App\Models\Departamento::all() as $dpto)
                <option value="{{ $dpto->id }}">{{ $dpto->nombre }}</option>
            @endforeach
        </select>

        @error('departamento_id')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    {{-- Estado de Disponibilidad --}}
    <div>
        <x-input-label :value="__('Disponibilidad en web')" />
        <label class="flex items-center cursor-pointer">
            <div class="relative">
                <input 
                    type="checkbox" 
                    wire:model="disponible" 
                    class="sr-only peer"
                    >
                <div class="w-10 h-5 bg-gray-300 rounded-full peer-checked:bg-blue-600"></div>
                <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition peer-checked:translate-x-5"></div>
            </div>
            <span class="ml-2 text-gray-700">Disponible</span>
        </label>
        @error('disponible')
        <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    {{-- Vencimientos --}}
    <div>
        <x-input-label :value="__('Vencimientos del Producto')" />
        
        @foreach ($vencimientos as $index => $v)
            <div class="flex flex-wrap gap-3 items-end bg-gray-50 p-3 rounded-lg mb-2 border border-gray-200">

                <div class="flex-1 min-w-[150px]">
                    <x-input-label :value="'Fecha de Vencimiento'" />
                    <input type="month" wire:model.defer="vencimientos.{{ $index }}.fecha_vencimiento" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring focus:ring-indigo-200">
                    @error("vencimientos.$index.fecha_vencimiento")
                        <p class="text-red-500 text-sm mt-1 truncate" title="{{ $message }}">
                            {{ Str::limit($message, 60) }}
                        </p>
                    @enderror
                </div>

                <div class="flex-1 min-w-[120px]">
                    <x-input-label :value="'Cantidad'" />
                    <x-text-input type="number" min="0" wire:model.defer="vencimientos.{{ $index }}.cantidad" class="w-full" placeholder="0" />
                    @error("vencimientos.$index.cantidad")
                        <p class="text-red-500 text-sm mt-1 truncate" title="{{ $message }}">
                            {{ Str::limit($message, 60) }}
                        </p>
                    @enderror

                </div>

                <div class="mt-6">
                    <button type="button" wire:click="eliminarVencimiento({{ $index }})" class="text-red-500 text-sm rounded hover:text-red-800">❌ Eliminar</button>
                </div>

            </div>
        @endforeach

        <button type="button" wire:click="agregarVencimiento" class="mt-2 px-2 py-2 rounded bg-green-600 text-white text-sm hover:bg-green-800">
            + Agregar otro vencimiento
        </button>
    </div>

    {{-- Oferta --}}
    <div class="border-t pt-6 mt-6">
        <x-input-label :value="__('Oferta del Producto')" />
        <div class="flex flex-wrap gap-3 items-end bg-gray-50 p-3 rounded-lg border border-gray-200">

            <div class="flex-1 min-w-[150px]">
                <x-input-label :value="'Precio de Oferta'" />
                <x-text-input type="number" wire:model.defer="oferta.precio_oferta" class="w-full sin-flechas" placeholder="Ej: 199.99" />
            </div>

            <div class="flex-1 min-w-[120px]">
                <x-input-label :value="'Cantidad'" />
                <x-text-input type="number" min="0" wire:model.defer="oferta.cantidad" class="w-full" placeholder="Ej: 5" />
            </div>

            {{-- Errores de oferta --}}
            <div class="mt-1 space-y-1">
                @error('oferta.precio_oferta')
                    <p class="text-red-500 text-sm truncate" title="{{ $message }}">{{ Str::limit($message, 80) }}</p>
                @enderror

                @error('oferta.cantidad')
                    <p class="text-red-500 text-sm truncate" title="{{ $message }}">{{ Str::limit($message, 80) }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- Botón Guardar --}}
    <x-primary-button>
        Crear Producto
    </x-primary-button>
</form>
