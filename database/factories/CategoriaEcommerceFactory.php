<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CategoriaEcommerce>
 */
class CategoriaEcommerceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = ucfirst(fake()->unique()->words(2, true));

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'categoria_padre_id' => null,
            'orden' => 0,
        ];
    }
}
