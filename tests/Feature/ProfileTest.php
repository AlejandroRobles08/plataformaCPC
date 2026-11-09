<?php

namespace Tests\Feature;

use App\Models\Integrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_includes_consejo_and_cargo_for_integrante(): void
    {
        $integrante = Integrante::factory()->create();

        $this->actingAs($integrante->user)
            ->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit', false)
                ->where('integrante.consejo', $integrante->consejo->nombre)
                ->where('integrante.cargo', $integrante->cargo));
    }

    public function test_integrante_cannot_update_name_or_email_from_profile(): void
    {
        $integrante = Integrante::factory()->create();
        $user = $integrante->user;
        $nombreOriginal = $user->name;
        $correoOriginal = $user->email;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Otro nombre',
                'email' => 'otro@example.com',
            ])
            ->assertForbidden();

        $user->refresh();

        $this->assertSame($nombreOriginal, $user->name);
        $this->assertSame($correoOriginal, $user->email);
    }

    public function test_password_cannot_be_changed_through_profile_update(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'nueva-contrasena',
                'password_confirmation' => 'nueva-contrasena',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Hash::check('nueva-contrasena', $user->refresh()->password));
    }

    private function crearSesion(string $id, User $user, int $minutosInactiva = 0): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'payload' => 'contenido-secreto',
            'last_activity' => now()->subMinutes($minutosInactiva)->getTimestamp(),
        ]);
    }

    public function test_profile_lists_only_the_current_users_valid_sessions(): void
    {
        $user = User::factory()->create();
        $idActual = str_repeat('a', 40);

        $this->crearSesion($idActual, $user);
        $this->crearSesion(str_repeat('b', 40), $user, 30);
        $this->crearSesion(str_repeat('c', 40), $user, (int) config('session.lifetime') + 5);
        $this->crearSesion(str_repeat('d', 40), User::factory()->create());

        $this->actingAs($user)
            ->withCookie(config('session.cookie'), $idActual)
            ->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->has('sesiones', 2)
                ->where('sesiones.0.actual', true)
                ->where('sesiones.0.dispositivo', 'Windows · Chrome')
                ->where('sesiones.1.actual', false)
                ->missing('sesiones.0.id')
                ->missing('sesiones.0.payload')
                ->missing('sesiones.0.ip_address'));
    }

    public function test_other_sessions_can_be_closed_keeping_the_current_one(): void
    {
        $user = User::factory()->create(['remember_token' => 'token-anterior']);
        $ajeno = User::factory()->create();
        $idActual = str_repeat('a', 40);

        $this->crearSesion($idActual, $user);
        $this->crearSesion(str_repeat('b', 40), $user);
        $this->crearSesion(str_repeat('d', 40), $ajeno);

        $this->actingAs($user)
            ->withCookie(config('session.cookie'), $idActual)
            ->delete('/profile/sessions', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertDatabaseHas('sessions', ['id' => $idActual]);
        $this->assertDatabaseMissing('sessions', ['id' => str_repeat('b', 40)]);
        $this->assertDatabaseHas('sessions', ['id' => str_repeat('d', 40)]);
        $this->assertNotSame('token-anterior', $user->refresh()->remember_token);
    }

    public function test_closing_other_sessions_keeps_remember_me_on_this_device(): void
    {
        $user = User::factory()->create();
        $cookie = Auth::guard()->getRecallerName();

        $this->actingAs($user)
            ->withCookie($cookie, 'valor-anterior')
            ->delete('/profile/sessions', ['password' => 'password'])
            ->assertCookie($cookie);

        $this->assertAuthenticatedAs($user);
    }

    public function test_closing_other_sessions_requires_the_correct_password(): void
    {
        $user = User::factory()->create();
        $this->crearSesion(str_repeat('b', 40), $user);

        $this->actingAs($user)
            ->from('/profile')
            ->delete('/profile/sessions', ['password' => 'incorrecta'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseHas('sessions', ['id' => str_repeat('b', 40)]);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $this->markTestSkipped('La ruta DELETE /profile está deshabilitada mientras se decide si se conserva.');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $this->markTestSkipped('La ruta DELETE /profile está deshabilitada mientras se decide si se conserva.');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
