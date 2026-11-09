<?php

namespace Tests\Feature\Auth;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\IntegranteBaja;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// Una persona dada de baja conserva su usuario, pero no puede ingresar: ve el aviso con el motivo
class CuentaDesactivadaTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consejo = Consejo::factory()->create(['nombre' => 'Consejo Centro']);
    }

    private function usuarioDadoDeBaja(string $motivo = 'inasistencia'): User
    {
        $integrante = Integrante::factory()->create(['consejo_id' => $this->consejo->id]);
        $user = $integrante->user;

        IntegranteBaja::create([
            'integrante_id' => $integrante->id,
            'user_id' => $user->id,
            'consejo_id' => $this->consejo->id,
            'nombre' => $integrante->nombre,
            'apellido' => $integrante->apellido,
            'motivo' => $motivo,
            'fecha_baja' => '2026-10-15',
        ]);
        $integrante->delete();

        return $user->fresh();
    }

    private function login(User $user, string $password = 'password')
    {
        return $this->post('/login', ['email' => $user->email, 'password' => $password]);
    }

    public function test_una_cuenta_dada_de_baja_no_puede_iniciar_sesion(): void
    {
        $user = $this->usuarioDadoDeBaja('renuncia');

        $this->login($user)->assertRedirect(route('cuenta.desactivada'));

        $this->assertGuest();
    }

    public function test_el_aviso_muestra_motivo_fecha_y_consejo(): void
    {
        $user = $this->usuarioDadoDeBaja('sancion');

        $this->followingRedirects()->login($user)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/CuentaDesactivada')
                ->where('motivo', 'sancion')
                ->where('fecha_baja', '2026-10-15')
                ->where('consejo', 'Consejo Centro'));
    }

    public function test_un_error_de_registro_muestra_un_aviso_generico_sin_motivo(): void
    {
        $user = $this->usuarioDadoDeBaja('error_registro');

        $this->followingRedirects()->login($user)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/CuentaDesactivada')
                ->where('motivo', null)
                ->where('fecha_baja', null)
                ->where('consejo', null));

        $this->assertGuest();
    }

    public function test_con_contrasena_incorrecta_no_se_revela_la_baja(): void
    {
        $user = $this->usuarioDadoDeBaja();

        $this->login($user, 'incorrecta')
            ->assertSessionHasErrors('email')
            ->assertSessionMissing('cuenta_desactivada');

        $this->assertGuest();
    }

    public function test_quien_vuelve_a_ser_integrante_puede_iniciar_sesion(): void
    {
        $user = $this->usuarioDadoDeBaja('fin_periodo');
        Integrante::factory()->create(['user_id' => $user->id, 'consejo_id' => $this->consejo->id]);

        $this->login($user)->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_una_sesion_abierta_se_cierra_al_quedar_dado_de_baja(): void
    {
        $user = $this->usuarioDadoDeBaja();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('cuenta.desactivada'));

        $this->assertGuest();
    }

    public function test_el_aviso_sin_datos_de_baja_regresa_al_login(): void
    {
        $this->get(route('cuenta.desactivada'))->assertRedirect(route('login'));
    }

    public function test_la_baja_guarda_el_usuario_del_integrante(): void
    {
        Storage::fake('public');
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = User::factory()->create()->assignRole('admin');
        $integrante = Integrante::factory()->create(['consejo_id' => $this->consejo->id]);

        $this->actingAs($admin)->post(route('integrantes.baja', $integrante), [
            'motivo' => 'renuncia',
            'fecha_baja' => '2026-11-01',
            'evidencia_pdf' => UploadedFile::fake()->create('baja.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('integrante_bajas', [
            'integrante_id' => $integrante->id,
            'user_id' => $integrante->user_id,
            'motivo' => 'renuncia',
        ]);
    }
}
