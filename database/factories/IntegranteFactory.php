<?php

namespace Database\Factories;

use App\Models\Consejo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Integrante>
 */
class IntegranteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'genero' => 'Prefiero no responder',
            'puesto' => 'Consejero',
            'correo' => fake()->unique()->safeEmail(),
            'consejo_id' => Consejo::factory(),
            'formula' => 1,
        ];
    }
}
