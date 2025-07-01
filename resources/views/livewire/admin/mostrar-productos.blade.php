<div class="bg-white p-4 rounded shadow">

    {{-- TARJETAS RESPONSIVE (solo en celular) --}}
    <div class="block md:hidden space-y-4">
        @foreach($productos as $producto)
            <div class="border rounded-lg p-4 shadow-sm bg-gray-50">
                <p><span class="font-bold">Nombre:</span> {{ $producto->nombre }}</p>
                <p><span class="font-bold">Precio:</span> ${{ number_format($producto->precio, 2) }}</p>
                <p><span class="font-bold">Stock total:</span> {{ $producto->stock_total }}</p>

                <p><span class="font-bold">Stock en oferta:</span>
                    @if($producto->ofertas)
                        {{ $producto->ofertas->cantidad }}u x 
                        <span class="text-green-600 font-semibold">
                            ${{ number_format($producto->ofertas->precio_oferta) }}
                        </span>
                    @else
                        <span class="text-gray-500">Sin oferta</span>
                    @endif
                </p>

                <p><span class="font-bold">Vencimiento más cercano:</span>
                    @if($producto->vencimiento_proximo)
                        {{ \Carbon\Carbon::parse($producto->vencimiento_proximo->fecha_vencimiento)->format('d/m/Y') }}
                        ({{ $producto->vencimiento_proximo->cantidad }})
                    @else
                        <span class="text-gray-500">Sin vencimientos</span>
                    @endif
                </p>

                <p><span class="font-bold">Imagen:</span> no</p>

                {{-- Acciones --}}
                <div class="flex gap-2 mt-4">
                    <a 
                        href="{{ route('admin.productos.edit', $producto->id) }}"
                        class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600"
                    >
                        ✏️ Editar
                    </a>
                    <button 
                        wire:click="eliminar({{ $producto->id }})"
                        class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
                    >
                        🗑️ Eliminar
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- TABLA ORIGINAL (solo visible en md y superior) --}}
    <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200 mt-4">
        <table class="min-w-full text-base border-collapse">
            <thead class="bg-sky-200 text-gray-700 rounded-t">
                <tr class="text-center">
                    <th class="px-4 py-3 border-b">Nombre</th>
                    <th class="px-4 py-3 border-b">Precio</th>
                    <th class="px-4 py-3 border-b">Stock Total</th>
                    <th class="px-4 py-3 border-b">Stock en oferta</th>
                    <th class="px-4 py-3 border-b">Vencimiento más Cercano</th>
                    <th class="px-4 py-3 border-b">Imagen</th>
                    <th class="px-4 py-3 border-b">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productos as $producto)
                    <tr class="border-t border-gray-300 hover:bg-gray-50 text-center">
                        <td class="px-4 py-2 border border-gray-200">{{ $producto->nombre }}</td>
                        <td class="px-4 py-2 border border-gray-200">${{ number_format($producto->precio, 2) }}</td>
                        <td class="px-4 py-2 border border-gray-200">{{ $producto->stock_total }}</td>
                        <td class="px-4 py-2 border border-gray-200">
                            @if($producto->ofertas)
                                {{ $producto->ofertas->cantidad }}u x 
                                <span class="text-green-600 font-semibold">
                                    ${{ number_format($producto->ofertas->precio_oferta) }}
                                </span>
                            @else
                                <span class="text-gray-500">Sin oferta</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 border border-gray-200">
                            @if($producto->vencimiento_proximo)
                                {{ \Carbon\Carbon::parse($producto->vencimiento_proximo->fecha_vencimiento)->format('d/m/Y') }}
                                ({{ $producto->vencimiento_proximo->cantidad }})
                            @else
                                <span class="text-gray-500">Sin vencimientos</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 border border-gray-200">no</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-2 justify-center">
                                <a 
                                    href="{{ route('admin.productos.edit', $producto->id) }}"
                                    class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600"
                                >
                                    ✏️ Editar
                                </a>
                                <button 
                                    wire:click="eliminar({{ $producto->id }})"
                                    class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
                                >
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $productos->links() }}
    </div>

</div>
