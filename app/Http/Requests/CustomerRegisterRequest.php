<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CustomerRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El e-mail no es válido.',
            'email.unique' => 'El email ya está registrado',
            'password.required' => 'La contraseña es obligatoria',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'password.min' => 'La contraseña debe tener al menos :min caracteres',
            'password.letters' => 'La contraseña debe contener al menos una letra',
            'password.mixedCase' => 'La contraseña debe contener al menos una letra mayúscula y una letra minúscula',
            'password.numbers' => 'La contraseña debe contener al menos un número',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo',
            'password.uncompromised' => 'La contraseña ha aparecido en una filtración de datos, por favor elige una mas segura',
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:customers,email'],
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
        ];
    }
}
