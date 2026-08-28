<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductoEcommerce>
 */
class ProductoEcommerceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'slug' => fake()->unique()->slug(),
            'descripcion' => fake()->sentence(),
            'activo' => true,
            'destacado' => false,
            'precio_online' => null,
            'stock_maximo_web' => null,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }
}
