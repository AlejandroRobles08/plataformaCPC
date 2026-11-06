<?php

namespace Tests\Feature\Asistencias;

use App\Models\Asistencia;
use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Sesion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaltasConsecutivasTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');

        $this->consejo = Consejo::factory()->create();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    private function integrante(): Integrante
    {
        $user = $this->usuario('integrante');

        return Integrante::factory()->create([
            'user_id' => $user->id,
            'correo' => $user->email,
            'consejo_id' => $this->consejo->id,
            'formula' => 1,
            'alta_completada_at' => now(),
        ]);
    }

    private function sesion(string $fecha): Sesion
    {
        return Sesion::firstOrCreate([
            'consejo_id' => $this->consejo->id,
            'fecha' => $fecha,
            'tipo_sesion' => 'ordinaria',
        ]);
    }

    private function registrar(Integrante $integrante, string $fecha, string $estado): Asistencia
    {
        return Asistencia::create([
            'sesion_id' => $this->sesion($fecha)->id,
            'integrante_id' => $integrante->id,
            'tipo_sesion' => 'ordinaria',
            'fecha' => $fecha,
            'estado' => $estado,
        ]);
    }

    private function color(Integrante $integrante): string
    {
        return Integrante::semaforosAsistencia([$integrante->id])[$integrante->id];
    }

    private function pasarLista(User $usuario, Sesion $sesion, Integrante $integrante, string $estado = 'asistio')
    {
        return $this->actingAs($usuario)->post(route('asistencias.sesion.store', $this->consejo), [
            'fecha' => $sesion->fecha,
            'tipo_sesion' => $sesion->tipo_sesion,
            'asistencias' => [['integrante_id' => $integrante->id, 'estado' => $estado]],
            'evidencia' => UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf'),
        ]);
    }

    public function test_el_color_depende_de_las_faltas_seguidas(): void
    {
        $casos = [
            'verde' => [[], ['asistio'], ['asistio', 'justificada']],
            'amarillo' => [
                ['falto'],
                ['falto', 'falto'],
                ['falto', 'asistio', 'falto', 'asistio', 'falto'],
                ['falto', 'falto', 'justificada', 'falto'],
            ],
            'rojo' => [
                ['falto', 'falto', 'falto'],
                ['asistio', 'falto', 'falto', 'falto'],
                ['falto', 'falto', 'falto', 'asistio'],
            ],
        ];

        foreach ($casos as $color => $secuencias) {
            foreach ($secuencias as $estados) {
                $this->assertSame($color, Integrante::colorSemaforo($estados), implode(',', $estados));
            }
        }
    }

    public function test_tres_faltas_seguidas_cuentan_aunque_sean_de_meses_distintos_y_lejanos(): void
    {
        $integrante = $this->integrante();

        // Se capturan en desorden: la racha se calcula por la fecha de la sesión
        $this->registrar($integrante, '2026-10-20', 'falto');
        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-06-15', 'falto');

        $this->assertSame('rojo', $this->color($integrante));
    }

    public function test_una_asistencia_entre_faltas_interrumpe_la_racha(): void
    {
        $integrante = $this->integrante();

        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-02-10', 'falto');
        $this->registrar($integrante, '2026-03-10', 'asistio');
        $this->registrar($integrante, '2026-04-10', 'falto');

        $this->assertSame('amarillo', $this->color($integrante));
    }

    public function test_el_index_envia_los_semaforos_y_el_integrante_solo_recibe_el_suyo(): void
    {
        $integrante = $this->integrante();
        $companero = $this->integrante();
        $this->registrar($companero, '2026-01-10', 'falto');

        $this->actingAs($integrante->user)
            ->get(route('asistencias.index', $this->consejo))
            ->assertInertia(fn (Assert $page) => $page
                ->where("semaforos.{$integrante->id}", 'verde')
                ->missing("semaforos.{$companero->id}"));

        $this->actingAs($this->usuario('admin'))
            ->get(route('asistencias.index', $this->consejo))
            ->assertInertia(fn (Assert $page) => $page
                ->where("semaforos.{$integrante->id}", 'verde')
                ->where("semaforos.{$companero->id}", 'amarillo'));
    }

    public function test_el_calendario_indica_quien_queda_fuera_del_pase_de_lista(): void
    {
        $conBaja = $this->integrante();
        $activo = $this->integrante();

        foreach (['2026-01-10', '2026-02-10', '2026-03-10'] as $fecha) {
            $this->registrar($conBaja, $fecha, 'falto');
        }
        $this->registrar($activo, '2026-03-10', 'falto');

        $this->actingAs($this->usuario('admin'))
            ->get(route('asistencia.calendar', $this->consejo))
            ->assertInertia(fn (Assert $page) => $page
                ->where('integrantesSinPaseLista', [$conBaja->id])
                ->has('integrantes', 2));
    }

    public function test_el_backend_no_registra_asistencia_a_quien_tiene_tres_faltas_seguidas(): void
    {
        $integrante = $this->integrante();

        foreach (['2026-01-10', '2026-02-10', '2026-03-10'] as $fecha) {
            $this->registrar($integrante, $fecha, 'falto');
        }

        $nueva = $this->sesion('2026-04-10');
        $this->pasarLista($this->usuario('admin'), $nueva, $integrante)->assertRedirect();

        $this->assertFalse(Asistencia::where('sesion_id', $nueva->id)->exists());
    }

    public function test_la_tercera_falta_se_registra_y_saca_al_integrante_de_la_siguiente_lista(): void
    {
        $integrante = $this->integrante();
        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-02-10', 'falto');

        $tercera = $this->sesion('2026-03-10');
        $this->pasarLista($this->usuario('admin'), $tercera, $integrante, 'falto');

        $this->assertTrue(Asistencia::where('sesion_id', $tercera->id)->exists());
        $this->assertSame('rojo', $this->color($integrante));
    }

    public function test_enviar_el_justificante_no_devuelve_al_integrante_pero_aprobarlo_si(): void
    {
        $integrante = $this->integrante();
        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-02-10', 'falto');
        $ultima = $this->registrar($integrante, '2026-03-10', 'falto');

        $ultima->update(['justificante' => 'justificantes/a.pdf', 'estado_justificante' => 'pendiente']);
        $this->assertSame('rojo', $this->color($integrante));

        $this->actingAs($this->usuario('admin'))
            ->patch(route('justificantes.aprobar', $ultima))
            ->assertRedirect();

        $this->assertSame('amarillo', $this->color($integrante));

        $nueva = $this->sesion('2026-04-10');
        $this->pasarLista($this->usuario('admin'), $nueva, $integrante);
        $this->assertTrue(Asistencia::where('sesion_id', $nueva->id)->exists());
    }

    public function test_rechazar_el_justificante_mantiene_al_integrante_fuera(): void
    {
        $integrante = $this->integrante();
        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-02-10', 'falto');
        $ultima = $this->registrar($integrante, '2026-03-10', 'falto');
        $ultima->update(['justificante' => 'justificantes/a.pdf', 'estado_justificante' => 'pendiente']);

        $this->actingAs($this->usuario('admin'))
            ->patch(route('justificantes.rechazar', $ultima), ['comentario_justificante' => 'Ilegible'])
            ->assertRedirect();

        $this->assertSame('rojo', $this->color($integrante));
    }

    public function test_el_super_admin_puede_corregir_un_registro_existente_de_quien_quedo_fuera(): void
    {
        $integrante = $this->integrante();
        $this->registrar($integrante, '2026-01-10', 'falto');
        $this->registrar($integrante, '2026-02-10', 'falto');
        $ultima = $this->registrar($integrante, '2026-03-10', 'falto');

        $this->pasarLista($this->usuario('super_admin'), $ultima->sesion, $integrante)->assertRedirect();

        $this->assertSame('asistio', $ultima->fresh()->estado);
        $this->assertSame('amarillo', $this->color($integrante));
    }
}
