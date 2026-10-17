<?php

namespace Tests\Feature\Asistencias;

use App\Models\Asistencia;
use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RutasProtegidasTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->consejo = Consejo::factory()->create(['descripcion' => 'Original']);
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    private function integrante(?Consejo $consejo = null, int $formula = 1): Integrante
    {
        $user = $this->usuarioConRol('integrante');

        return Integrante::factory()->create([
            'user_id' => $user->id,
            'correo' => $user->email,
            'consejo_id' => ($consejo ?? $this->consejo)->id,
            'formula' => $formula,
        ]);
    }

    private function sesionConAsistencias(Integrante ...$integrantes): Sesion
    {
        $sesion = Sesion::create([
            'consejo_id' => $this->consejo->id,
            'fecha' => '2026-10-01',
            'tipo_sesion' => 'ordinaria',
        ]);

        foreach ($integrantes as $integrante) {
            Asistencia::create([
                'sesion_id' => $sesion->id,
                'integrante_id' => $integrante->id,
                'tipo_sesion' => 'ordinaria',
                'fecha' => '2026-10-01',
                'estado' => 'falto',
                'evidencia' => 'evidencias/lista.pdf',
            ]);
        }

        return $sesion;
    }

    // ---------------------------------------------------------------
    // Descripción del consejo
    // ---------------------------------------------------------------

    public function test_integrante_e_invitado_no_editan_la_descripcion_del_consejo(): void
    {
        foreach ([$this->integrante()->user, $this->usuarioConRol('invitado')] as $usuario) {
            $this->actingAs($usuario)
                ->patch(route('consejos.descripcion.update', $this->consejo), ['descripcion' => 'Hackeado'])
                ->assertForbidden();
        }

        $this->assertSame('Original', $this->consejo->fresh()->descripcion);
    }

    public function test_admin_y_super_admin_editan_la_descripcion_del_consejo(): void
    {
        foreach (['admin', 'super_admin'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol))
                ->patch(route('consejos.descripcion.update', $this->consejo), ['descripcion' => "Editada por {$rol}"])
                ->assertRedirect();

            $this->assertSame("Editada por {$rol}", $this->consejo->fresh()->descripcion);
        }
    }

    // ---------------------------------------------------------------
    // Calendario de sesiones
    // ---------------------------------------------------------------

    public function test_integrante_no_ve_el_calendario_de_otro_consejo(): void
    {
        $deOtroConsejo = $this->integrante(Consejo::factory()->create());

        $this->actingAs($deOtroConsejo->user)
            ->get(route('asistencia.calendar', $this->consejo))
            ->assertForbidden();
    }

    public function test_invitado_no_ve_calendarios(): void
    {
        $this->actingAs($this->usuarioConRol('invitado'))
            ->get(route('asistencia.calendar', $this->consejo))
            ->assertForbidden();
    }

    public function test_integrante_ve_sesiones_de_su_consejo_sin_datos_de_otros_integrantes(): void
    {
        $integrante = $this->integrante();
        $companero = $this->integrante();
        $this->sesionConAsistencias($integrante, $companero);

        $this->actingAs($integrante->user)
            ->get(route('asistencia.calendar', $this->consejo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Asistencia/Calendar')
                ->has('sesiones', 1)
                ->where('integrante.id', $integrante->id)
                ->has('integrantes', 0)
                ->has('asistencias', 0));
    }

    public function test_admin_ve_el_calendario_completo_de_cualquier_consejo(): void
    {
        $uno = $this->integrante();
        $dos = $this->integrante();
        $this->sesionConAsistencias($uno, $dos);

        $this->actingAs($this->usuarioConRol('admin'))
            ->get(route('asistencia.calendar', $this->consejo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('sesiones', 1)
                ->has('integrantes', 2)
                ->has('asistencias', 2)
                ->where('integrante', null));
    }

    // ---------------------------------------------------------------
    // Evidencias de sesión
    // ---------------------------------------------------------------

    public function test_integrante_no_ve_evidencias_ni_de_su_propio_consejo(): void
    {
        $integrante = $this->integrante();
        $this->sesionConAsistencias($integrante);

        $this->actingAs($integrante->user)
            ->get(route('asistencias.evidencias', $this->consejo))
            ->assertForbidden();
    }

    public function test_admin_ve_evidencias(): void
    {
        $this->sesionConAsistencias($this->integrante());

        $this->actingAs($this->usuarioConRol('admin'))
            ->get(route('asistencias.evidencias', $this->consejo))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sesiones', 1));
    }

    // ---------------------------------------------------------------
    // Rutas sin sesión y rutas eliminadas
    // ---------------------------------------------------------------

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('asistencia.calendar', $this->consejo))->assertRedirect(route('login'));
        $this->get(route('asistencias.evidencias', $this->consejo))->assertRedirect(route('login'));
        $this->patch(route('consejos.descripcion.update', $this->consejo), ['descripcion' => 'x'])
            ->assertRedirect(route('login'));
    }

    public function test_la_ruta_rota_asistencia_show_ya_no_existe(): void
    {
        $this->assertFalse(Route::has('asistencia.show'));
    }
}
