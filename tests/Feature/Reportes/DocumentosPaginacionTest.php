<?php

namespace Tests\Feature\Reportes;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosPaginacionTest extends TestCase
{
    use RefreshDatabase;

    private Consejo $consejo;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->consejo = Consejo::factory()->create();
        $this->admin = User::factory()->create()->assignRole('admin');

        Integrante::factory()->count(12)->create(['consejo_id' => $this->consejo->id]);
    }

    public function test_el_checklist_muestra_10_integrantes_por_pagina(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reportes.documentos', $this->consejo))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reportes/Documentos')
                ->has('integrantes.data', 10)
                ->where('integrantes.total', 12)
                ->missing('integrantesExportacion'));

        $this->actingAs($this->admin)
            ->get(route('reportes.documentos', [$this->consejo, 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('integrantes.data', 2));
    }

    public function test_la_exportacion_incluye_a_todos_los_integrantes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reportes.documentos', $this->consejo))
            ->assertInertia(fn (Assert $page) => $page
                ->reloadOnly('integrantesExportacion', fn (Assert $reload) => $reload
                    ->has('integrantesExportacion', 12)));
    }

    public function test_la_busqueda_encuentra_integrantes_de_cualquier_pagina(): void
    {
        $buscado = Integrante::factory()->create([
            'consejo_id' => $this->consejo->id,
            'nombre' => 'Zacarías',
            'apellido_paterno' => 'Quiroga',
            'apellido_materno' => 'Xolalpa',
        ]);

        foreach (['Zacarías', 'Xolalpa', 'Xolalpa Zacarías'] as $busqueda) {
            $this->actingAs($this->admin)
                ->get(route('reportes.documentos', [$this->consejo, 'busqueda' => $busqueda]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('integrantes.total', 1)
                    ->where('integrantes.data.0.id', $buscado->id)
                    ->where('filtros.busqueda', $busqueda));
        }
    }

    public function test_la_busqueda_no_incluye_integrantes_de_otro_consejo(): void
    {
        Integrante::factory()->create([
            'consejo_id' => Consejo::factory()->create()->id,
            'nombre' => 'Zacarías',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reportes.documentos', [$this->consejo, 'busqueda' => 'Zacarías']))
            ->assertInertia(fn (Assert $page) => $page->where('integrantes.total', 0));
    }

    public function test_la_paginacion_conserva_la_busqueda_y_la_exportacion_sigue_completa(): void
    {
        Integrante::factory()->count(11)->create([
            'consejo_id' => $this->consejo->id,
            'nombre' => 'Zacarías',
        ]);

        $this->actingAs($this->admin)
            ->get(route('reportes.documentos', [$this->consejo, 'busqueda' => 'Zacarías']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('integrantes.data', 10)
                ->where('integrantes.total', 11)
                ->where('integrantes.next_page_url', fn ($url) => str_contains($url, 'busqueda='))
                ->reloadOnly('integrantesExportacion', fn (Assert $reload) => $reload
                    ->has('integrantesExportacion', 23)));
    }
}
