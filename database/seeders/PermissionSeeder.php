<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            //-------------archivo digital------------
            'archivo_digital.ver',

            'integrantes.crear',
            'integrantes.editar',
            'integrantes.baja',

            'documentos.subir',
            'documentos.ver',
            'documentos.observar',
            'documentos.aprobar',

            // ---------consejos------
            'consejos.ver',

            // -----------asistencias-------
            'asistencias.ver',
            'asistencias.crear',
            'sesiones.ver',

            // -------periodos------------
            'periodos.ver',
            'periodos.crear',
            'periodos.solicitar_reeleccion',
            'periodos.validar_reeleccion',
            'periodos.rechazar_reeleccion',

            'periodos.documentos.ver',
            'periodos.documentos.subir',

            // -------------convocatorias------
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',

            //-------- reportes--------------
            'reportes.ver',

            // -----------usuarios------------
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',

            // ------------postulaciones---------------
            'postulaciones.crear',
            'postulaciones.ver',
            'postulaciones.validar',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }
    }
}