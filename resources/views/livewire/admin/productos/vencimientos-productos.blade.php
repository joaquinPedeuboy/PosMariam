<div class="relative">
    <livewire:admin.productos.filtrarProductos
    event-name="terminosBusqueda"
    :show-vencimiento="true"
    :show-termino="true"
    />
    

    <div class="bg-white p-4 rounded shadow mt-10">

        {{-- TARJETAS RESPONSIVE (solo en celular) --}}
        <div class="block md:hidden space-y-4">
            @forelse($productos as $producto)
                <div class="border rounded-lg p-4 shadow-sm bg-gray-50">
                    <p><span class="font-bold">Nombre:</span> {{ $producto->nombre }}</p>
                    <p><span class="font-bold">Precio:</span> ${{ number_format($producto->precio, 2) }}</p>
                    
                    <div class="mt-2">
                        <p class="font-bold">Vencimientos disponibles:</p>
                        @if($producto->vencimientos->count())
                            <div class="flex flex-wrap gap-2 mt-1">
                            @foreach($producto->vencimientos as $v)
                                @php
                                    // parseamos YYYY-MM
                                    try {
                                    $vDate = \Carbon\Carbon::createFromFormat('Y-m', $v->fecha_vencimiento)->startOfMonth();
                                    } catch (\Throwable $e) {
                                    $vDate = \Carbon\Carbon::parse($v->fecha_vencimiento)->startOfMonth();
                                    }
                                    $monthsDiff = \Carbon\Carbon::now()->diffInMonths($vDate, false);
                                    // Lógica de color:
                                    // monthsDiff < 0 (pasado) o == 0 (este mes) => rojo
                                    // monthsDiff between 1 and 2 => naranja
                                    // monthsDiff between 2 and 6 => yellow
                                    // monthsDiff >= 7 => verde
                                    if ($monthsDiff <= 0) {
                                        $dot = 'bg-red-500';
                                    } elseif ($monthsDiff <= 2) {
                                        $dot = 'bg-orange-500';
                                    } elseif($monthsDiff <= 6) {
                                        $dot = 'bg-yellow-500';
                                    } else {
                                        $dot = 'bg-green-500';
                                    }

                                    $isSelectedMonth = isset($fecha) && $fecha === $v->fecha_vencimiento;
                                @endphp

                                <div class="flex items-center space-x-2 text-sm {{ $isSelectedMonth ? 'bg-sky-50 p-1 rounded' : '' }}">
                                <span class="w-3 h-3 rounded-full {{ $dot }} inline-block"></span>
                                <span class="font-medium">{{ \Carbon\Carbon::parse($vDate)->format('Y/m') }}</span>
                                <span class="text-gray-500">({{ $v->cantidad }}u)</span>
                                </div>
                            @endforeach
                            </div>
                        @else
                            <p class="text-gray-500">Sin vencimientos</p>
                        @endif
                        </div>

                        {{-- Acciones --}}
                        <div class="flex gap-2 mt-4">
                            <a href="{{ route('admin.productos.edit', $producto->id) }}" class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">
                                ✏️ Editar
                            </a>
                            <button wire:click="eliminar({{ $producto->id }})" class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="p-3 text-center text-sm text-gray-600">No hay productos que mostrar</p>
                @endforelse
        </div>

        {{-- TABLA ORIGINAL (solo visible en md y superior) --}}
        <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200 mt-4 relative">
            <table class="min-w-full text-base border-collapse">
                <thead class="bg-sky-200 text-gray-700 rounded-t">
                    <tr class="text-center">
                        <th class="px-4 py-3 border-b">Nombre</th>
                        <th class="px-4 py-3 border-b">Precio</th>
                        <th class="px-4 py-3 border-b">Vencimientos disponibles</th>
                        <th class="px-4 py-3 border-b">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- ESTA FILA APARECE CUANDO LIVEWIRE ESTÁ CARGANDO --}}
                    <tr wire:loading.class.remove="hidden" wire:target="termino,leerDatosFormulario,search" class="hidden bg-white">
                        <td colspan="7" class="py-8 text-center">
                            <svg class="animate-spin h-8 w-8 mx-auto text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                        </td>
                    </tr>
                    @forelse($productos as $producto)
                        <tr class="border-t border-gray-300 hover:bg-gray-50 text-center">
                            <td class="px-4 py-2 border border-gray-200">{{ $producto->nombre }}</td>
                            <td class="px-4 py-2 border border-gray-200">${{ number_format($producto->precio, 2) }}</td>

                            {{-- VENCIMIENTOS DISPONIBLES --}}
                            <td class="px-4 py-4 border border-gray-200 text-center">
                                @if($producto->vencimientos->count())
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($producto->vencimientos as $v)
                                            @php
                                                // convertir YYYY-MM a Carbon
                                                try {
                                                    $vDate = \Carbon\Carbon::createFromFormat('Y-m', $v->fecha_vencimiento)->startOfMonth();
                                                } catch (\Throwable $e) {
                                                    $vDate = \Carbon\Carbon::parse($v->fecha_vencimiento)->startOfMonth();
                                                }
                                                $monthsDiff = \Carbon\Carbon::now()->diffInMonths($vDate, false);

                                                if ($monthsDiff <= 0) {
                                                    $dot = 'bg-red-500';
                                                } elseif ($monthsDiff <= 2) {
                                                    $dot = 'bg-orange-500';
                                                } elseif($monthsDiff <= 6) {
                                                    $dot = 'bg-yellow-500';
                                                } else {
                                                    $dot = 'bg-green-500';
                                                }

                                                $isSelectedMonth = isset($fecha) && $fecha === $v->fecha_vencimiento;
                                            @endphp

                                            <div class="flex items-center gap-2 p-1 bg-gray-50 rounded-md shadow {{ $isSelectedMonth ? 'bg-sky-100 rounded' : '' }}">
                                                <span class="w-3 h-3 rounded-full {{ $dot }} inline-block"></span>
                                                <div class="text-sm">
                                                    <div class="font-medium">{{ $vDate->format('Y/m') }}</div>
                                                    <div class="text-xs text-gray-500">{{ $v->cantidad }} u</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-500">Sin vencimientos</span>
                                @endif
                            </td>

                            <td class="p-0">
                                <div class="flex justify-evenly">
                                    <a 
                                        href="{{ route('admin.productos.edit', $producto->id) }}"
                                        class="bg-yellow-500 text-white px-3 py-2 rounded text-sm hover:bg-yellow-600"
                                    >
                                        ✏️ Editar
                                    </a>
                                    <button 
                                        wire:click="eliminar({{ $producto->id }})"
                                        class="bg-red-500 text-white px-3 py-2 rounded text-sm hover:bg-red-600"
                                    >
                                        🗑️ Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <p class="p-3 text-center text-sm text-gray-600">No hay productos que mostrar</p>
                    @endforelse
                </tbody>
            </table>
            {{-- OVERLAY SPINNER --}}
            <div
            wire:loading
            wire:target="buscar"
            class="absolute inset-0 bg-white bg-opacity-70 flex items-center justify-center"
            >
                <svg class="animate-spin h-8 w-8 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </div>
        </div>

        <div class="mt-4">
            {{ $productos->links() }}
        </div>

    </div>
</div>


