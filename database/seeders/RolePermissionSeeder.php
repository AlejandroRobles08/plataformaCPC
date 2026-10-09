<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        //-------------SUPER ADMIN-------------
        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
        ]);
        $superAdmin->syncPermissions([]);


        //------------ADMIN-------------
        $admin = Role::firstOrCreate([
            'name' => 'admin',
        ]);

        $admin->syncPermissions([

            // archivo digital
            'archivo_digital.ver',
            'documentos.ver',
            'documentos.observar',
            'documentos.aprobar',

            // consejos
            'consejos.ver',

            // asistencias
            'asistencias.ver',
            'asistencias.crear',
            'sesiones.ver',

            // periodos
            'periodos.ver',
            'periodos.crear',
            'periodos.validar_reeleccion',
            'periodos.rechazar_reeleccion',
            'periodos.documentos.ver',

            // convocatorias
            'convocatorias.ver',
            'convocatorias.crear',
            'convocatorias.editar',

            // reportes
            'reportes.ver',

            // usuarios
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',

            // postulaciones
            'postulaciones.ver',
            'postulaciones.validar',
        ]);


        //-----INTEGRANTE------
        $integrante = Role::firstOrCreate([
            'name' => 'integrante',
        ]);

        $integrante->syncPermissions([

            // archivo digital
            'archivo_digital.ver',
            'documentos.subir',
            'documentos.ver',

            // consejos
            'consejos.ver',

            // asistencias
            'asistencias.ver',
            'sesiones.ver',

            //periodos
            'periodos.ver',
            'periodos.solicitar_reeleccion',
            'periodos.documentos.ver',
            'periodos.documentos.subir',

            // convocatorias
            'convocatorias.ver',
        ]);


        //-----INVITADO------
        $invitado = Role::firstOrCreate([
            'name' => 'invitado',
        ]);
        $invitado->syncPermissions([

            // postulaciones
            'postulaciones.crear',
            'postulaciones.ver',
        ]);
    }
}