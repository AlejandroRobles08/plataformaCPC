<?php

namespace Tests\Feature;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ConsejosVisiblesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_integrante_solo_ve_su_consejo_en_todos_los_modulos(): void
    {
        [$suyo, $otro] = Consejo::factory()->count(2)->create();
        $user = User::factory()->create()->assignRole('integrante');
        Integrante::factory()->create([
            'user_id' => $user->id,
            'correo' => $user->email,
            'consejo_id' => $suyo->id,
        ]);

        foreach (['consejos.index', 'consejos.asistencias', 'consejos.legalidad'] as $ruta) {
            $this->actingAs($user)
                ->get(route($ruta))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->has('consejos', 1)
                    ->where('consejos.0.id', $suyo->id)
                );
        }
    }

    public function test_admin_ve_todos_los_consejos(): void
    {
        Consejo::factory()->count(3)->create();

        $this->actingAs(User::factory()->create()->assignRole('admin'))
            ->get(route('consejos.index'))
            ->assertInertia(fn (Assert $page) => $page->has('consejos', 3));
    }

    public function test_usuario_sin_consejo_no_ve_ninguno(): void
    {
        Consejo::factory()->count(2)->create();

        $this->actingAs(User::factory()->create()->assignRole('invitado'))
            ->get(route('consejos.index'))
            ->assertInertia(fn (Assert $page) => $page->has('consejos', 0));
    }
}
