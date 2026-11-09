<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\DispositivoSesion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $integrante = $request->user()->integrante;

        return Inertia::render('Profile/Edit', [
            'integrante' => $integrante ? [
                'consejo' => $integrante->consejo?->nombre,
                'cargo' => $integrante->cargo,
            ] : null,
            'sesiones' => $this->sesionesVigentes($request),
        ]);
    }

    /**
     * Close every session of the user except the current one.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        // Sin esto, la cookie de "Recordarme" volvería a iniciar sesión en los otros dispositivos
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Este dispositivo conserva su "Recordarme" con el token nuevo
        if ($request->cookies->has(Auth::guard()->getRecallerName())) {
            Auth::login($user, true);
        }

        return Redirect::route('profile.edit');
    }

    /**
     * Sessions still in use, without the session id or its payload.
     */
    private function sesionesVigentes(Request $request): array
    {
        $idActual = $request->session()->getId();
        $vigentesDesde = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('last_activity', '>=', $vigentesDesde)
            ->orderByDesc('last_activity')
            ->get(['id', 'user_agent', 'last_activity'])
            ->map(fn ($sesion) => [
                'dispositivo' => DispositivoSesion::nombre($sesion->user_agent),
                'movil' => DispositivoSesion::esMovil($sesion->user_agent),
                'ultima_actividad' => Carbon::createFromTimestamp($sesion->last_activity)
                    ->locale('es')
                    ->diffForHumans(),
                'actual' => $sesion->id === $idActual,
            ])
            ->sortByDesc('actual')
            ->values()
            ->all();
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Los datos del integrante se administran desde su expediente (IntegranteController)
        abort_if($request->user()->integrante()->exists(), 403);

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Navegación completa: ver AuthenticatedSessionController::destroy()
        return Inertia::location(url('/'));
    }
}
