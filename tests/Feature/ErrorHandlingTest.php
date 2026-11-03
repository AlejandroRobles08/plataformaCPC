<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    public function test_un_403_de_spatie_muestra_un_mensaje_comprensible_segun_el_rol(): void
    {
        $this->actingAs($this->usuarioConRol('invitado'))
            ->get(route('users.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->where('titulo', 'Acceso restringido')
                ->where('mensaje', 'No tienes permisos para acceder a este módulo o realizar esta acción.')
                ->where('sugerencia', fn ($sugerencia) => str_contains($sugerencia, 'cuenta de invitado'))
            );

        $this->actingAs($this->usuarioConRol('integrante'))
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sugerencia', fn ($sugerencia) => str_contains($sugerencia, 'comunícate con la administración'))
            );
    }

    public function test_se_conserva_el_mensaje_escrito_en_abort(): void
    {
        $this->actingAs($this->usuarioConRol('integrante'))
            ->get(route('postulaciones.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('mensaje', 'No tienes acceso al módulo de postulaciones.')
            );
    }

    public function test_una_accion_denegada_regresa_a_la_pagina_anterior_con_una_alerta(): void
    {
        $this->actingAs($this->usuarioConRol('invitado'))
            ->from(route('postulaciones.index'))
            ->withHeaders(['X-Inertia' => 'true'])
            ->post(route('postulaciones.invitados.store'))
            ->assertStatus(303)
            ->assertRedirect(route('postulaciones.index'))
            ->assertSessionHas('error_global.titulo', 'Acceso restringido');
    }

    public function test_una_ruta_inexistente_muestra_la_pagina_de_error(): void
    {
        $this->get('/ruta-que-no-existe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('titulo', 'Página no encontrada')
                ->where('autenticado', false)
            );
    }

    public function test_un_error_del_servidor_no_expone_detalles_tecnicos(): void
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/_prueba/error', fn () => throw new \RuntimeException('SQLSTATE[42S22] columna desconocida'));

        $this->get('/_prueba/error')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('titulo', 'Algo salió mal')
                ->where('mensaje', fn ($mensaje) => ! str_contains($mensaje, 'SQLSTATE'))
            );
    }

    public function test_las_peticiones_json_reciben_el_mensaje_en_espanol(): void
    {
        $this->actingAs($this->usuarioConRol('integrante'))
            ->getJson(route('users.index'))
            ->assertForbidden()
            ->assertExactJson(['message' => 'No tienes permisos para acceder a este módulo o realizar esta acción.']);
    }

    public function test_los_errores_de_validacion_se_muestran_en_espanol(): void
    {
        $this->actingAs($this->usuarioConRol('admin'))
            ->post(route('postulaciones.invitados.store'), [])
            ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
    }
}
