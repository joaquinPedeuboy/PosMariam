<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake()->unique()->words(3, true)),
            'codigo_barras' => fake()->unique()->numerify('#############'),
            'precio' => fake()->randomFloat(2, 1000, 50000),
            'departamento_id' => null,
        ];
    }
}
