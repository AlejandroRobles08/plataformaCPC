<?php

namespace Tests\Feature\Integrantes;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

// Cada fórmula se compone de una persona titular y una suplente: el cargo no se repite
class CargoPorFormulaTest extends TestCase
{
    use RefreshDatabase;

    private const TITULAR = 'Persona Consejera Titular';
    private const SUPLENTE = 'Persona Consejera Suplente';

    private Consejo $consejo;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->consejo = Consejo::factory()->create();
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    private function integrante(int $formula, ?string $cargo, ?Consejo $consejo = null): Integrante
    {
        return Integrante::factory()->create([
            'consejo_id' => ($consejo ?? $this->consejo)->id,
            'formula' => $formula,
            'cargo' => $cargo,
        ]);
    }

    private function crear(int $formula, string $cargo)
    {
        return $this->actingAs($this->admin)->post(route('integrantes.store'), [
            'nombre' => 'Nueva',
            'apellido_paterno' => 'Persona',
            'email' => 'nueva@ejemplo.com',
            'consejo_id' => $this->consejo->id,
            'formula' => $formula,
            'cargo' => $cargo,
        ]);
    }

    private function datosDeEdicion(Integrante $integrante, array $cambios): array
    {
        return $cambios + [
            'nombre' => $integrante->nombre,
            'apellido_paterno' => $integrante->apellido_paterno,
            'cargo' => $integrante->cargo,
            'correo' => $integrante->correo,
        ];
    }

    public function test_no_se_crea_un_integrante_con_un_cargo_ya_ocupado_en_la_formula(): void
    {
        $this->integrante(1, self::TITULAR);

        $this->crear(1, self::TITULAR)->assertSessionHasErrors('cargo');

        $this->assertDatabaseCount('integrantes', 1);
        $this->assertDatabaseMissing('users', ['email' => 'nueva@ejemplo.com']);
    }

    public function test_el_cargo_se_bloquea_en_ambos_sentidos(): void
    {
        $this->integrante(1, self::SUPLENTE);

        $this->crear(1, self::SUPLENTE)->assertSessionHasErrors('cargo');
    }

    public function test_se_crea_el_integrante_con_el_cargo_libre_de_la_formula(): void
    {
        $this->integrante(1, self::TITULAR);

        $this->crear(1, self::SUPLENTE)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('integrantes', [
            'correo' => 'nueva@ejemplo.com',
            'formula' => 1,
            'cargo' => self::SUPLENTE,
        ]);
    }

    public function test_el_mismo_cargo_se_permite_en_otra_formula_y_en_otro_consejo(): void
    {
        $this->integrante(1, self::TITULAR);
        $this->integrante(2, self::TITULAR, Consejo::factory()->create());

        $this->crear(2, self::TITULAR)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('integrantes', ['correo' => 'nueva@ejemplo.com']);
    }

    public function test_un_integrante_sin_cargo_no_ocupa_ninguno(): void
    {
        $this->integrante(1, null);

        $this->crear(1, self::TITULAR)->assertSessionHasNoErrors();
    }

    public function test_no_se_edita_el_cargo_a_uno_ya_ocupado_en_la_formula(): void
    {
        $this->integrante(1, self::TITULAR);
        $suplente = $this->integrante(1, self::SUPLENTE);

        $this->actingAs($this->admin)
            ->put(route('integrantes.update', $suplente), $this->datosDeEdicion($suplente, ['cargo' => self::TITULAR]))
            ->assertSessionHasErrors('cargo');

        $this->assertSame(self::SUPLENTE, $suplente->fresh()->cargo);
    }

    public function test_editar_otros_datos_no_se_bloquea_aunque_ya_exista_un_duplicado(): void
    {
        $this->integrante(1, self::TITULAR);
        $duplicado = $this->integrante(1, self::TITULAR);

        $this->actingAs($this->admin)
            ->put(route('integrantes.update', $duplicado), $this->datosDeEdicion($duplicado, ['nombre' => 'Corregido']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Corregido', $duplicado->fresh()->nombre);
    }

    public function test_no_se_mueve_a_una_formula_donde_su_cargo_ya_esta_ocupado(): void
    {
        $this->integrante(2, self::TITULAR);
        $integrante = $this->integrante(1, self::TITULAR);

        $this->actingAs($this->admin)
            ->patch(route('integrantes.mover', $integrante), ['formula' => 2])
            ->assertSessionHasErrors('cargo');

        $this->assertSame(1, (int) $integrante->fresh()->formula);
    }

    public function test_se_mueve_a_una_formula_donde_su_cargo_esta_libre(): void
    {
        $this->integrante(2, self::SUPLENTE);
        $integrante = $this->integrante(1, self::TITULAR);

        $this->actingAs($this->admin)
            ->patch(route('integrantes.mover', $integrante), ['formula' => 2])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, (int) $integrante->fresh()->formula);
    }
}
