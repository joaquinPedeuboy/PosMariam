<div class="mt-10">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

        @forelse ($users as $user)
                <div class="p-6 bg-white border-b border-gray-200 md:flex md:justify-between md:items-center">
                    <div class="space-y-3">
                        <p class="text-xl font-bold">{{ $user->name }} {{ $user->surname }}</p>
                        <p class="text-sm text-gray-600 font-bold">{{ $user->email }}</p>
                    </div>
                    <div class="flex flex-col md:flex-row items-stretch gap-3 mt-5 md:mt-0">
                        <!-- Botón Editar -->
                        <a href="{{ route('admin.users.edit', $user->id) }}"
                        class="bg-amber-500 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase text-center hover:bg-amber-600">
                            Editar
                        </a>

                        <!-- Botón Eliminar -->
                        <button wire:click="eliminar({{ $user->id }})"
                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase text-center hover:bg-red-600">
                            Eliminar
                        </button>
                    </div>
                </div>
        @empty
                <p class="p-3 text-center text-sm text-gray-600">No hay administradores registrados</p>
        @endforelse

        {{-- <div class="mt-4">
            {{ $users->links() }}
        </div> --}}
    </div>
</div>

