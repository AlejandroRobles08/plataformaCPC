<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Consejo>
 */
class ConsejoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Consejo ' . fake()->unique()->words(2, true),
            'descripcion' => fake()->sentence(),
        ];
    }
}
