<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
        {
            return [
                'nombre' => fake()->firstName(),
                'apellido' => fake()->lastName(),
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password123', // se hashea solo, por el cast del modelo
                'telefono' => fake()->numerify('##########'),
            ];
        }
}
