<?php

namespace Tests\Feature\Documentos;

use App\Models\Consejo;
use App\Models\Docu;
use App\Models\Integrante;
use App\Models\Postulacion;
use App\Models\PostulacionDocumento;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosIntegranteTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Storage::fake(Docu::DISCO);
        Storage::fake('public');

        $this->consejo = Consejo::factory()->create();
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    private function integrante(int $formula = 1): Integrante
    {
        $user = $this->usuarioConRol('integrante');

        return Integrante::factory()->create([
            'user_id' => $user->id,
            'correo' => $user->email,
            'consejo_id' => $this->consejo->id,
            'formula' => $formula,
        ]);
    }

    private function documentoCon(Integrante $integrante, array $atributos = []): Docu
    {
        $documento = Docu::factory()->create(['integrante_id' => $integrante->id] + $atributos);
        Docu::disco()->put($documento->archivo, '%PDF-1.4 contenido');

        return $documento;
    }

    private function pdf(string $nombre = 'documento.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nombre, 100, 'application/pdf');
    }

    // ---------------------------------------------------------------
    // Consulta del expediente
    // ---------------------------------------------------------------

    public function test_invitado_sin_sesion_es_redirigido_al_login(): void
    {
        $documento = $this->documentoCon($this->integrante());

        $this->get(route('docu.show', $documento))->assertRedirect(route('login'));
        $this->get(route('docu.download', $documento))->assertRedirect(route('login'));
        $this->get(route('docu.index', $documento->integrante_id))->assertRedirect(route('login'));
    }

    public function test_integrante_consulta_su_propio_expediente(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante);

        $this->actingAs($integrante->user)
            ->get(route('docu.index', $integrante))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Docu/Index')
                ->where('integrante.documentos.0.id', $documento->id)
                ->missing('integrante.documentos.0.archivo'));
    }

    public function test_integrante_no_consulta_expediente_de_otro_integrante_aunque_sea_de_su_formula(): void
    {
        $integrante = $this->integrante(formula: 1);
        $companero = $this->integrante(formula: 1);
        $documento = $this->documentoCon($companero);

        $this->actingAs($integrante->user);

        $this->get(route('docu.index', $companero))->assertForbidden();
        $this->get(route('docu.show', $documento))->assertForbidden();
        $this->get(route('docu.download', $documento))->assertForbidden();
    }

    public function test_integrante_ve_y_descarga_su_documento(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante);

        $this->actingAs($integrante->user);

        $this->get(route('docu.show', $documento))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('docu.download', $documento))
            ->assertOk()
            ->assertDownload();
    }

    public function test_admin_y_super_admin_consultan_cualquier_expediente(): void
    {
        $documento = $this->documentoCon($this->integrante());

        foreach (['admin', 'super_admin'] as $rol) {
            $this->actingAs($this->usuarioConRol($rol));

            $this->get(route('docu.index', $documento->integrante_id))->assertOk();
            $this->get(route('docu.show', $documento))->assertOk();
            $this->get(route('docu.download', $documento))->assertOk();
        }
    }

    public function test_usuario_invitado_no_consulta_documentos(): void
    {
        $documento = $this->documentoCon($this->integrante());

        $this->actingAs($this->usuarioConRol('invitado'));

        $this->get(route('docu.index', $documento->integrante_id))->assertForbidden();
        $this->get(route('docu.show', $documento))->assertForbidden();
    }

    public function test_usuario_integrante_dado_de_baja_pierde_acceso(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante);
        $user = $integrante->user;

        $integrante->delete();

        $this->actingAs($user)
            ->get(route('docu.show', $documento->id))
            ->assertNotFound();
    }

    public function test_documento_sin_archivo_fisico_responde_404(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante);
        Docu::disco()->delete($documento->archivo);

        $this->actingAs($integrante->user)
            ->get(route('docu.show', $documento))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Carga de documentos
    // ---------------------------------------------------------------

    public function test_integrante_sube_documentos_al_disco_privado(): void
    {
        $integrante = $this->integrante();

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), [
                'ine' => $this->pdf(),
                'curriculum_vitae' => $this->pdf(),
            ])
            ->assertSessionHasNoErrors();

        $documentos = $integrante->documentos()->get();
        $this->assertCount(2, $documentos);

        foreach ($documentos as $documento) {
            $this->assertSame(Docu::PENDIENTE, $documento->estatus);
            $this->assertStringStartsWith("documentos/{$integrante->id}/", $documento->archivo);
            Docu::disco()->assertExists($documento->archivo);
            Storage::disk('public')->assertMissing($documento->archivo);
        }
    }

    public function test_integrante_no_sube_documentos_al_expediente_de_otro(): void
    {
        $integrante = $this->integrante();
        $otro = $this->integrante();

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $otro), ['ine' => $this->pdf()])
            ->assertForbidden();

        $this->assertSame(0, $otro->documentos()->count());
        $this->assertEmpty(Docu::disco()->allFiles());
    }

    public function test_admin_no_sube_documentos_en_nombre_del_integrante(): void
    {
        $integrante = $this->integrante();

        $this->actingAs($this->usuarioConRol('admin'))
            ->post(route('docu.store', $integrante), ['ine' => $this->pdf()])
            ->assertForbidden();
    }

    public function test_solo_se_aceptan_pdf(): void
    {
        $integrante = $this->integrante();

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), [
                'ine' => UploadedFile::fake()->create('ataque.html', 1, 'text/html'),
            ])
            ->assertSessionHasErrors('ine');

        $this->assertSame(0, $integrante->documentos()->count());
        $this->assertEmpty(Docu::disco()->allFiles());
    }

    public function test_se_rechazan_archivos_mayores_a_2_mb(): void
    {
        $integrante = $this->integrante();

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), [
                'ine' => UploadedFile::fake()->create('grande.pdf', 3000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('ine');
    }

    public function test_enviar_sin_archivos_marca_error(): void
    {
        $integrante = $this->integrante();

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), [])
            ->assertSessionHasErrors('documentos');
    }

    public function test_documento_aprobado_o_en_revision_no_se_reemplaza(): void
    {
        $integrante = $this->integrante();
        $aprobado = $this->documentoCon($integrante, ['tipo' => 'ine', 'estatus' => Docu::APROBADO]);
        $pendiente = $this->documentoCon($integrante, ['tipo' => 'curriculum_vitae']);

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), [
                'ine' => $this->pdf(),
                'curriculum_vitae' => $this->pdf(),
            ])
            ->assertSessionHasErrors(['ine', 'curriculum_vitae']);

        $this->assertSame($aprobado->archivo, $aprobado->fresh()->archivo);
        $this->assertSame($pendiente->archivo, $pendiente->fresh()->archivo);
        $this->assertCount(2, Docu::disco()->allFiles());
    }

    public function test_documento_rechazado_se_reemplaza_y_vuelve_a_revision(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante, [
            'estatus' => Docu::RECHAZADO,
            'observacion' => 'Ilegible',
            'validado_por' => $this->usuarioConRol('admin')->id,
            'validado_at' => now(),
        ]);
        $anterior = $documento->archivo;

        $this->actingAs($integrante->user)
            ->post(route('docu.store', $integrante), ['ine' => $this->pdf()])
            ->assertSessionHasNoErrors();

        $documento->refresh();
        $this->assertSame(Docu::PENDIENTE, $documento->estatus);
        $this->assertNull($documento->observacion);
        $this->assertNull($documento->validado_por);
        $this->assertNotSame($anterior, $documento->archivo);
        Docu::disco()->assertExists($documento->archivo);
        Docu::disco()->assertMissing($anterior);
    }

    // ---------------------------------------------------------------
    // Validación (aprobar / rechazar)
    // ---------------------------------------------------------------

    public function test_integrante_no_valida_documentos_ni_los_propios(): void
    {
        $integrante = $this->integrante();
        $documento = $this->documentoCon($integrante);

        $this->actingAs($integrante->user);

        $this->patch(route('docu.aprobar', $documento))->assertForbidden();
        $this->patch(route('docu.rechazar', $documento), ['observacion' => 'x'])->assertForbidden();

        $this->assertSame(Docu::PENDIENTE, $documento->fresh()->estatus);
    }

    public function test_admin_aprueba_documento(): void
    {
        $admin = $this->usuarioConRol('admin');
        $documento = $this->documentoCon($this->integrante());

        $this->actingAs($admin)->patch(route('docu.aprobar', $documento))->assertRedirect();

        $documento->refresh();
        $this->assertSame(Docu::APROBADO, $documento->estatus);
        $this->assertSame($admin->id, $documento->validado_por);
        $this->assertNotNull($documento->validado_at);
    }

    public function test_admin_rechaza_con_observacion_obligatoria(): void
    {
        $admin = $this->usuarioConRol('admin');
        $documento = $this->documentoCon($this->integrante());

        $this->actingAs($admin)
            ->patch(route('docu.rechazar', $documento), ['observacion' => ''])
            ->assertSessionHasErrors('observacion');

        $this->actingAs($admin)
            ->patch(route('docu.rechazar', $documento), ['observacion' => 'La INE está vencida'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Docu::RECHAZADO, $documento->fresh()->estatus);
        $this->assertSame('La INE está vencida', $documento->fresh()->observacion);
    }

    public function test_documento_aprobado_no_se_rechaza(): void
    {
        $documento = $this->documentoCon($this->integrante(), ['estatus' => Docu::APROBADO]);

        $this->actingAs($this->usuarioConRol('admin'))
            ->patch(route('docu.rechazar', $documento), ['observacion' => 'Cambio de opinión'])
            ->assertSessionHas('error');

        $this->assertSame(Docu::APROBADO, $documento->fresh()->estatus);
    }

    // ---------------------------------------------------------------
    // Alta desde postulación y migración de archivos existentes
    // ---------------------------------------------------------------

    public function test_aprobar_postulacion_copia_documentos_al_expediente_privado(): void
    {
        $postulante = $this->usuarioConRol('invitado');
        $postulacion = Postulacion::create([
            'user_id' => $postulante->id,
            'nombre' => 'Ana',
            'apellido_paterno' => 'López',
            'correo' => $postulante->email,
            'estatus' => 'pendiente',
            'fecha_postulacion' => now(),
        ]);

        foreach (['ine', 'bajo_protesta'] as $tipo) {
            $ruta = "postulaciones/{$tipo}.pdf";
            Storage::disk('public')->put($ruta, '%PDF-1.4');
            PostulacionDocumento::create(['postulacion_id' => $postulacion->id, 'tipo' => $tipo, 'archivo' => $ruta]);
        }

        $this->actingAs($this->usuarioConRol('admin'))
            ->post(route('postulaciones.aprobar', $postulacion), [
                'fecha_validacion' => now()->toDateString(),
                'acta_resolucion' => $this->pdf('acta.pdf'),
                'consejo_id' => $this->consejo->id,
            ])
            ->assertSessionHasNoErrors();

        $integrante = Integrante::where('user_id', $postulante->id)->firstOrFail();
        $documentos = $integrante->documentos()->get()->keyBy('tipo');

        $this->assertEqualsCanonicalizing(['ine', 'bajo_protesta_art_170'], $documentos->keys()->all());

        foreach ($documentos as $documento) {
            $this->assertStringStartsWith("documentos/{$integrante->id}/", $documento->archivo);
            Docu::disco()->assertExists($documento->archivo);
        }

        // La postulación conserva su propio archivo
        Storage::disk('public')->assertExists('postulaciones/ine.pdf');
    }

    public function test_comando_mueve_documentos_existentes_al_disco_privado(): void
    {
        $integrante = $this->integrante();

        $propio = Docu::factory()->create([
            'integrante_id' => $integrante->id,
            'tipo' => 'ine',
            'archivo' => "documentos/{$integrante->id}/viejo.pdf",
        ]);
        $dePostulacion = Docu::factory()->create([
            'integrante_id' => $integrante->id,
            'tipo' => 'curriculum_vitae',
            'archivo' => 'postulaciones/cv.pdf',
        ]);
        Storage::disk('public')->put($propio->archivo, '%PDF ine');
        Storage::disk('public')->put($dePostulacion->archivo, '%PDF cv');

        $this->artisan('documentos:privatizar', ['--dry-run' => true])->assertSuccessful();
        Docu::disco()->assertMissing("documentos/{$integrante->id}/viejo.pdf");

        $this->artisan('documentos:privatizar')->assertSuccessful();

        Docu::disco()->assertExists($propio->fresh()->archivo);
        Docu::disco()->assertExists($dePostulacion->fresh()->archivo);
        $this->assertSame("documentos/{$integrante->id}/cv.pdf", $dePostulacion->fresh()->archivo);

        Storage::disk('public')->assertMissing("documentos/{$integrante->id}/viejo.pdf");
        Storage::disk('public')->assertExists('postulaciones/cv.pdf');

        // Volver a ejecutarlo no cambia nada
        $this->artisan('documentos:privatizar')->assertSuccessful();
        $this->assertSame("documentos/{$integrante->id}/cv.pdf", $dePostulacion->fresh()->archivo);
    }
}
