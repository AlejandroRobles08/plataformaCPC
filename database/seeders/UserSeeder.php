<?php

namespace Database\Seeders;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEEDER_USER_PASSWORD');

        if (empty($password)) {
            throw new \RuntimeException(
                'SEEDER_USER_PASSWORD no está configurada.'
            );
        }

        $users = [

            // super admin

            [
                'name' => 'Juan Perez',
                'email' => 'admin@test.com',
                'role' => 'super_admin',
            ],

            [
                'name' => 'Super Admin Prueba',
                'email' => 'superadmin@plataformacpc.test',
                'role' => 'super_admin',
            ],


            // admin
            [
                'name' => 'Jesus Alejandro',
                'email' => 'jesus.alejandro@plataformacpc.test',
                'role' => 'admin',
            ],
            [
                'name' => 'Admin Prueba',
                'email' => 'admin@plataformacpc.test',
                'role' => 'admin',
            ],

            // integrante
            [
                'name' => 'Usuario Integrante',
                'email' => 'integrante@ejemplo.com',
                'role' => 'integrante',

                'integrante' => [
                    'nombre' => 'Usuario',
                    'apellido_paterno' => 'Integrante',
                    'puesto' => 'Consejero',
                    'correo' => 'integrante@ejemplo.com',
                ],
            ],

            [
                'name' => 'Integrante Prueba',
                'email' => 'integrante.prueba@plataformacpc.test',
                'role' => 'integrante',

                'integrante' => [
                    'nombre' => 'Integrante',
                    'apellido_paterno' => 'Prueba',
                    'puesto' => 'Consejero',
                    'correo' => 'integrante.prueba@plataformacpc.test',
                ],
            ],


            // invitado
            [
                'name' => 'Invitado Prueba',
                'email' => 'invitado@plataformacpc.test',
                'role' => 'invitado',
            ],
        ];

        // obtener un consejo existente para los integrantes de prueba
        $consejo = Consejo::first();
        if (!$consejo) {
            throw new \RuntimeException(
                'No existe ningún consejo. ConsejoSeeder debe ejecutarse antes de UserSeeder.'
            );
        }
        foreach ($users as $data) {
            $user = User::updateOrCreate(
                [
                    'email' => $data['email'],
                ],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($password),
                ]
            );
            $user->syncRoles([
                $data['role'],
            ]);


            // crear o actualizar integrante asociado
            if (
                $data['role'] === 'integrante'
                && isset($data['integrante'])
            ) {

                Integrante::updateOrCreate(
                    [
                        'user_id' => $user->id,
                    ],
                    [
                        'nombre' => $data['integrante']['nombre'],
                        'apellido_paterno' => $data['integrante']['apellido_paterno'],
                        'puesto' => $data['integrante']['puesto'],
                        'correo' => $data['integrante']['correo'],
                        'consejo_id' => $consejo->id,
                    ]
                );
            }
        }
    }
}