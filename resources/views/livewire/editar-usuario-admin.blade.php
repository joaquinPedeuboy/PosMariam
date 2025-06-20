<form class="md:w-1/2 space-y-5" wire:submit.prevent="editarAdministrador">
    <p class="text-sm text-gray-600">
        {{ __("Actualice la información del perfil y la dirección de correo electrónico de su cuenta.") }}
    </p>
    <p class="text-sm text-gray-600">
        {{ __('Asegúrese de que su cuenta utilice una contraseña larga y aleatoria para mantener la seguridad.') }}
    </p>
    <!-- Name -->
    <div>
        <x-input-label for="name" :value="__('Nombre Administrador')" />
        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" wire:model.live="name" :value="old('name')" placeholder="Nombre Administrador" />

        @error('name')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    <!-- Surname -->
    <div class="mt-4">
        <x-input-label for="surname" :value="__('Apellido Administrador')" />
        <x-text-input id="surname" class="block mt-1 w-full" type="text" name="surname" wire:model.live="surname" :value="old('surname')" placeholder="Apellido Administrador" />
        
        @error('surname')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    <!-- Email Address -->
    <div class="mt-4">
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" wire:model.live="email" :value="old('email')" placeholder="Email" />
    
        @error('email')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    <!-- Password -->
    <div x-data="{ show: false }" class="mt-4 relative min-h-[100px]">
        <x-input-label for="password" :value="__('Password')" />
        <x-text-input id="password" class="block mt-1 w-full pr-10" wire:model.live="password" x-bind:type="show ? 'text' : 'password'" name="password" placeholder="Nueva Password" />
        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 transform -translate-y-1/2 flex items-center text-sm text-gray-500">>
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-.422 1.54-1.16 2.946-2.145 4.12M15 15l5 5" />
            </svg>
            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a10.054 10.054 0 012.636-4.415m5.896-2.13a10.055 10.055 0 014.48 1.43m.216.274l3.798 3.798M4.222 4.222l15.556 15.556" />
            </svg>
        </button>
    
        @error('password')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    <!-- Confirm Password -->
    <div x-data="{ show: false }" class="mt-4 relative min-h-[100px]">
        <x-input-label for="password_confirmation" :value="__('Repetir Password')" />
        <x-text-input id="password_confirmation" class="block mt-1 w-full pr-10" wire:model.live="password_confirmation" x-bind:type="show ? 'text' : 'password'" name="password_confirmation" placeholder="Repetir Password" />
        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 transform -translate-y-1/2 flex items-center text-sm text-gray-500">>
            <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-.422 1.54-1.16 2.946-2.145 4.12M15 15l5 5" />
            </svg>
            <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a10.054 10.054 0 012.636-4.415m5.896-2.13a10.055 10.055 0 014.48 1.43m.216.274l3.798 3.798M4.222 4.222l15.556 15.556" />
            </svg>
        </button>
    
        @error('password_confirmation')
            <livewire:mostrar-alerta :message="$message" />
        @enderror
    </div>

    <x-primary-button class="w-full justify-center bg-indigo-600 hover:bg-indigo-700">
        {{ __('Editar Administrador') }}
    </x-primary-button>
</form>