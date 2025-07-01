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
                        <button wire:click="$dispatch('mostrarAlerta',{userId: {{ $user->id }} })"
                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-xs font-bold uppercase text-center hover:bg-red-700">
                            Eliminar
                        </button>
                    </div>
                </div>
        @empty
                <p class="p-3 text-center text-sm text-gray-600">No hay administradores registrados</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>


@push('scripts')
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Alerta de sweerAlert2 para eliminar la vacante --}}
    <script>

        Livewire.on('mostrarAlerta', ({userId}) => {
                Swal.fire({
                    title: "¿Eliminar Administrador?",
                    text: "Un Administrador eliminado no se puede recuperar",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: "Si, ¡Eliminar!",
                    cancelButtonText: "Cancelar"
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Eliminar la vacante en el servidor
                        Livewire.dispatch('eliminarAdministrador', ({userId}))
                        Swal.fire({
                        title: "Se eliminó el Administrador",
                        text: "Eliminado Correctamente",
                        icon: "success"
                    });
                }
                });
        })
        
    </script>
@endpush
