<?php

namespace Database\Factories;

use App\Models\Docu;
use App\Models\Integrante;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Docu>
 */
class DocuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'integrante_id' => Integrante::factory(),
            'tipo' => 'ine',
            'archivo' => fn (array $atributos) => Docu::carpetaDe($atributos['integrante_id']) . '/' . Str::random(40) . '.pdf',
            'estatus' => Docu::PENDIENTE,
        ];
    }

    public function aprobado(): static
    {
        return $this->state(['estatus' => Docu::APROBADO, 'validado_at' => now()]);
    }

    public function rechazado(): static
    {
        return $this->state(['estatus' => Docu::RECHAZADO, 'observacion' => 'Ilegible', 'validado_at' => now()]);
    }
}
