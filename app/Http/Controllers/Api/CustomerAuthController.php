<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerRegisterRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(CustomerRegisterRequest $request)
    {
        $validated = $request->validated();

        $customer = Customer::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'telefono' => $validated['telefono'] ?? null,
        ]);

        $redirect = $request->input('redirect', '/mi-cuenta');

        if (! in_array($redirect, ['/mi-cuenta', '/checkout'], true)) {
            $redirect = '/mi-cuenta';
        }

        $customer->sendEmailVerificationNotification($redirect);

        $token = $customer->createToken('storefront')->plainTextToken;

        return response()->json([
            'customer' => $customer,
            'token' => $token,
        ], 201);
    }

    public function login(CustomerLoginRequest $request)
    {
        $credentials = $request->validated();

        $customer = Customer::where('email', $credentials['email'])->first();

        if (! $customer || ! Hash::check($credentials['password'], $customer->password)) {
            return response()->json([
                'message' => 'El e-mail o la contraseña no son correctos.',
            ], 401);
        }

        $token = $customer->createToken('storefront')->plainTextToken;

        return response()->json([
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /** * Actualizar datos personales. */ 
    public function updateProfile(Request $request) 
    { 
        $customer = $request->user(); 

        $validated = $request->validate([ 
            'nombre' => 'required|string|max:100', 
            'apellido' => 'nullable|string|max:100', 
            'telefono' => 'nullable|string|max:30', 
        ]); 
        
        $customer->update([ 
            'nombre' => $validated['nombre'], 
            'apellido' => $validated['apellido'] ?? null, 
            'telefono' => $validated['telefono'] ?? null, 
        ]); 

        return response()->json([ 
            'message' => 'Datos actualizados correctamente.', 
            'customer' => $customer->fresh(), 
            ]); 
    }

    /** * Cambiar email. */
    public function updateEmail(Request $request) 
    {
        $customer = $request->user(); 
        
        $validated = $request->validate([ 
            'email' => [ 
                'required', 
                'email', 
                'max:255', 
                Rule::unique('customers', 'email')->ignore($customer->id), 
            ],
            'password' => 'required|string', 
        ]);

        if (! Hash::check($validated['password'], $customer->password)) { 
            throw ValidationException::withMessages([ 
                'password' => 'La contraseña actual no es correcta.', 
                ]); 
            } 
            $customer->update([ 
                'email' => $validated['email'], 
            ]); 
        
        return response()->json([ 
            'message' => 'Email actualizado correctamente.', 
            'customer' => $customer->fresh(), 
        ]);
    }

    /** * Cambiar contraseña. */ 
    public function updatePassword(Request $request) 
    { 
        $customer = $request->user(); 
        
        $validated = $request->validate([ 
            'current_password' => 'required|string', 
            'password' => [ 
                'required', 
                'confirmed', 
                Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
                ], 
            ]); 
            
            if (! Hash::check($validated['current_password'], $customer->password)) {
                throw ValidationException::withMessages([ 
                    'current_password' => 'La contraseña actual no es correcta.', 
                ]); 
            } 
            
            $customer->update([ 
                'password' => $validated['password'], 
            ]); 
        
        return response()->json([ 
            'message' => 'Contraseña actualizada correctamente.', 
        ]); 
    }

}
