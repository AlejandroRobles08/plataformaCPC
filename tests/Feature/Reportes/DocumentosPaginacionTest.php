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
}
