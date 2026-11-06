<?php

namespace Tests\Feature\Convocatorias;

use App\Models\Consejo;
use App\Models\Convocatoria;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosObligatoriosTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake('public');

        $this->consejo = Consejo::factory()->create();
        $this->usuario = User::factory()->create()->givePermissionTo('convocatorias.crear');
    }

    private function guardar(array $datos)
    {
        // Inertia envía los booleanos como '1' / '0' al usar FormData
        return $this->actingAs($this->usuario)->post(route('convocatorias.store', $this->consejo), array_merge([
            'tipo_sesion' => 'ordinaria',
            'fecha' => '2026-11-05',
            'estado_convocatoria' => '0',
            'estado_sesion' => '0',
        ], $datos));
    }

    private function pdf(string $nombre = 'archivo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 100, 'application/pdf');
    }

    public function test_convocatoria_realizada_sin_acta_no_se_guarda(): void
    {
        $this->guardar(['estado_convocatoria' => '1'])
            ->assertSessionHasErrors([
                'convocatoria_documento' => 'El acta de convocatoria es obligatoria; debes adjuntarla.',
            ]);

        $this->assertSame(0, Convocatoria::count());
    }

    public function test_sesion_realizada_exige_los_cuatro_documentos(): void
    {
        $this->guardar(['estado_convocatoria' => '1', 'estado_sesion' => '1'])
            ->assertSessionHasErrors([
                'convocatoria_documento' => 'El acta de convocatoria es obligatoria; debes adjuntarla.',
                'documento' => 'El acta de sesión es obligatoria; debes adjuntarla.',
                'lista_asistencia' => 'La lista de asistencia es obligatoria; debes adjuntarla.',
                'evidencia' => 'La evidencia fotográfica es obligatoria; debes adjuntarla.',
            ]);

        $this->assertSame(0, Convocatoria::count());
    }

    public function test_si_falta_un_solo_documento_de_sesion_no_se_guarda(): void
    {
        $this->guardar([
            'estado_convocatoria' => '1',
            'estado_sesion' => '1',
            'convocatoria_documento' => $this->pdf(),
            'documento' => $this->pdf(),
            'lista_asistencia' => $this->pdf(),
        ])->assertSessionHasErrors(['evidencia']);

        $this->assertSame(0, Convocatoria::count());
    }

    public function test_convocatoria_realizada_con_acta_se_guarda(): void
    {
        $this->guardar([
            'estado_convocatoria' => '1',
            'convocatoria_documento' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Convocatoria::count());
    }

    public function test_sesion_realizada_con_todos_los_documentos_se_guarda(): void
    {
        $this->guardar([
            'estado_convocatoria' => '1',
            'estado_sesion' => '1',
            'convocatoria_documento' => $this->pdf(),
            'documento' => $this->pdf(),
            'lista_asistencia' => $this->pdf(),
            'evidencia' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        $convocatoria = Convocatoria::sole();

        foreach (['convocatoria_documento', 'documento', 'lista_asistencia', 'evidencia'] as $campo) {
            Storage::disk('public')->assertExists($convocatoria->{$campo});
        }
    }

    public function test_sin_marcar_realizada_no_se_exigen_documentos(): void
    {
        $this->guardar([])->assertSessionHasNoErrors();

        $this->assertSame(1, Convocatoria::count());
    }
}
