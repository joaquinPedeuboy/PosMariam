<div class="bg-gray-100 py-2">
    <h2 class="text-2xl text-gray-600 text-center font-extrabold my-5">Buscar y Filtrar Productos</h2>

  <form wire:submit.prevent="leerDatosFormulario">
    @php
        $visibleFilters = collect([
            $showTermino,
            $showStockOrder,
            $showVencimiento
        ])->filter()->count();

        $gridCols = match($visibleFilters) {
            1 => 'justify-center',
            2 => 'grid gap-6 grid-cols sm:grid sm:gap-6 sm:grid-cols md:flex md:justify-evenly md:items-center',
            default => 'md:grid-cols-3'
        };
    @endphp

    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
      <div class="{{ $gridCols }}">

        @if($showTermino)
          <div class="flex flex-col items-center mb-2">
            <label class="block mb-1 font-semibold text-gray-700">Termino de Busqueda</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35M5 11a6 6 0 1112 0 6 6 0 01-12 0z"/>
                </svg>
              </span>
              <input
                wire:model.lazy="termino"
                type="text"
                placeholder="Nombre o código"
                class="pl-10 pr-4 py-2 rounded-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm md:w-96"
              />
            </div>
          </div>
        @endif

        @if($showStockOrder)
          <div class="flex flex-col items-center mb-2">
            <label class="block mb-1 font-semibold text-gray-700">Stock máximo</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h11M9 21V3m0 18a9 9 0 100-18 9 9 0 000 18z" />
                </svg>
              </span>
              <input
                wire:model.lazy="stock"
                type="number"
                placeholder="Ej: 20"
                class="w-full pl-10 pr-4 py-2 rounded-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm"
              />
            </div>
          </div>

          <div class="flex justify-center items-center space-x-2">
            <input
              type="checkbox"
              wire:model="sinVencimientos"
              id="sinVencimientos"
              class="h-4 w-4 text-indigo-600 border-gray-300 rounded"
            />
            <label for="sinVencimientos" class="text-gray-700">Sin vencimientos</label>
          </div>
        @endif

        @if($showVencimiento)
          <div class="flex flex-col items-center">
            <label class="block mb-1 font-semibold text-gray-700">Fecha de vencimiento</label>
            <div class="relative">
              <input
                wire:model.defer="fecha"
                type="month"
                class="pl-10 pr-4 py-2 rounded-md border border-gray-300 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm md:w-64"
              />
            </div>
          </div>
        @endif

        {{-- Botón --}}
        <div class="flex items-end justify-center">
          <button
            type="submit"
            wire:loading.attr="disabled"
            wire:target="leerDatosFormulario"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded flex items-center gap-2 w-full md:w-auto"
          >
            <svg
              wire:loading
              wire:target="leerDatosFormulario"
              class="animate-spin h-5 w-5 text-white"
              xmlns="http://www.w3.org/2000/svg"
              fill="none" viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
            </svg>
            <span wire:loading.remove wire:target="leerDatosFormulario">Buscar Producto</span>
            <span wire:loading wire:target="leerDatosFormulario">Buscando…</span>
          </button>
        </div>

      </div>
    </div>
  </form>

</div>

