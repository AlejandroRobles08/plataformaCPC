<?php

namespace Database\Seeders;

use App\Models\Consejo;
use Illuminate\Database\Seeder;

class ConsejoSeeder extends Seeder
{
    public function run(): void
    {
        $consejos = [
            [
                'id' => 1,
                'nombre' => 'Discapacidad',
                'descripcion' => 'Agregar descripción',
            ],
            [
                'id' => 2,
                'nombre' => 'Bienestar Animal',
                'descripcion' => 'Impulsar políticas de protección, cuidado y trato digno hacia los animales, promoviendo la tenencia responsable y la conciencia ciudadana.',
            ],
            [
                'id' => 3,
                'nombre' => 'Centro Histórico y Patrimonio Edificado',
                'descripcion' => 'Coadyuvar en la conservación, rehabilitación y aprovechamiento del patrimonio arquitectónico y cultural del centro histórico de la ciudad.',
            ],
                ['id' => 4,
                'nombre' => 'Ecología y Medio Ambiente',
                'descripcion' => 'Promover políticas y acciones de educación ecológica y desarrollo sustentable.',
            ],
            [
                'id' => 5,
                'nombre' => 'Seguridad Pública',
                'descripcion' => 'Coadyuvar en el fortalecimiento de la prevención del delito y la construcción de entornos seguros.',
            ],
            [
                'id' => 6,
                'nombre' => 'Salud',
                'descripcion' => 'Fomentar acciones para la promoción de la salud, la prevención de enfermedades y la mejora de los servicios municipales en la materia.'
            ],
            [
                'id' => 7,
                'nombre' => 'Impulso a las artesanías',
                'descripcion' => 'Agregar descripción',
            ],
            [
                'id' => 8,
                'nombre' => 'Niñez y Adolescencia',
                'descripcion' => 'Promover acciones y políticas que garanticen los derechos, bienestar y desarrollo integral de las infancias y adolescencias en el municipio.',
            ],
            [
                'id' => 9,
                'nombre' => 'Cultura',
                'descripcion' => 'Fomentar el acceso a la vida cultural del municipio, el fortalecimiento de las expresiones artísticas y el patrimonio cultural.',
            ],
            [
                'id' => 10,
                'nombre' => 'Obras y Servicios Públicos',
                'descripcion' => 'Promover mejoras en la infraestructura, mantenimiento y calidad de los servicios públicos municipales.',
            ],
            [
                'id' => 11,
                'nombre' => 'Igualdad de Género, y Prevención, Atención y 
                            Erradicación de la Violencia contra la Mujer.',
                'descripcion' => 'Agregar descripción'
            ],
        ];

        foreach ($consejos as $datos) {
            $consejo = Consejo::findOrNew($datos['id']);

            $consejo->id = $datos['id'];
            $consejo->nombre = $datos['nombre'];
            $consejo->descripcion = $datos['descripcion'];

            $consejo->save();
        }
    }
}
