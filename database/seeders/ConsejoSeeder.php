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
                'icono' => 'accessible_forward',
            ],
            [
                'id' => 2,
                'nombre' => 'Bienestar Animal',
                'descripcion' => 'Impulsar políticas de protección, cuidado y trato digno hacia los animales, promoviendo la tenencia responsable y la conciencia ciudadana.',
                'icono' => 'pets',
            ],
            [
                'id' => 3,
                'nombre' => 'Centro Histórico y Patrimonio Edificado',
                'descripcion' => 'Coadyuvar en la conservación, rehabilitación y aprovechamiento del patrimonio arquitectónico y cultural del centro histórico de la ciudad.',
                'icono' => 'account_balance',
            ],
                ['id' => 4,
                'nombre' => 'Ecología y Medio Ambiente',
                'descripcion' => 'Promover políticas y acciones de educación ecológica y desarrollo sustentable.',
                'icono' => 'eco',
            ],
            [
                'id' => 5,
                'nombre' => 'Seguridad Pública',
                'descripcion' => 'Coadyuvar en el fortalecimiento de la prevención del delito y la construcción de entornos seguros.',
                'icono' => 'local_police',
            ],
            [
                'id' => 6,
                'nombre' => 'Salud',
                'descripcion' => 'Fomentar acciones para la promoción de la salud, la prevención de enfermedades y la mejora de los servicios municipales en la materia.',
                'icono' => 'medical_services',
            ],
            [
                'id' => 7,
                'nombre' => 'Impulso a las artesanías',
                'descripcion' => 'Agregar descripción',
                'icono' => 'palette',
            ],
            [
                'id' => 8,
                'nombre' => 'Niñez y Adolescencia',
                'descripcion' => 'Promover acciones y políticas que garanticen los derechos, bienestar y desarrollo integral de las infancias y adolescencias en el municipio.',
                'icono' => 'child_care',
            ],
            [
                'id' => 9,
                'nombre' => 'Cultura',
                'descripcion' => 'Fomentar el acceso a la vida cultural del municipio, el fortalecimiento de las expresiones artísticas y el patrimonio cultural.',
                'icono' => 'theater_comedy',
            ],
            [
                'id' => 10,
                'nombre' => 'Obras y Servicios Públicos',
                'descripcion' => 'Promover mejoras en la infraestructura, mantenimiento y calidad de los servicios públicos municipales.',
                'icono' => 'construction',
            ],
            [
                'id' => 11,
                'nombre' => 'Igualdad de Género, y Prevención, Atención y 
                            Erradicación de la Violencia contra la Mujer.',
                'descripcion' => 'Agregar descripción',
                'icono' => 'female',
            ],
        ];

        foreach ($consejos as $datos) {
            $consejo = Consejo::findOrNew($datos['id']);

            $consejo->id = $datos['id'];
            $consejo->nombre = $datos['nombre'];
            $consejo->descripcion = $datos['descripcion'];
            $consejo->icono = $datos['icono'];

            $consejo->save();
        }
    }
}
