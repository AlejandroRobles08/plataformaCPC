<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\CuentaDesactivada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Cuenta dada de baja: la contraseña es correcta, pero no se permite el ingreso.
        // Se revisa después de autenticar para que el motivo solo lo vea la persona dueña de la cuenta.
        if ($baja = $request->user()->bajaVigente()) {
            CuentaDesactivada::cerrarSesion($request, $baja);

            return redirect()->route('cuenta.desactivada');
        }

        // Aviso de privacidad: se acepta una sola vez, sobre el mismo login.
        // Si aún no lo acepta, no se completa el inicio de sesión y el login muestra el modal;
        // al aceptarlo, el formulario se reenvía con privacy_accepted.
        $user = $request->user();
        if (! $user->privacy_accepted) {
            if (! $request->boolean('privacy_accepted')) {
                Auth::guard('web')->logout();

                throw ValidationException::withMessages([
                    'privacy' => 'Debes aceptar el aviso de privacidad para ingresar.',
                ]);
            }

            $user->update([
                'privacy_accepted' => true,
                'privacy_accepted_at' => now(),
            ]);
        }

        $request->session()->regenerate();

        // La persona invitada solo usa el módulo de postulaciones (segundo filtro: documentos)
        $inicio = $user->hasRole('invitado') ? 'postulaciones.index' : 'dashboard';

        return redirect()->intended(route($inicio, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Navegación completa, no una visita de Inertia: en una misma carga de la app, Inertia
        // restaura las páginas de "atrás/adelante" desde history.state sin consultar al servidor,
        // así que se seguirían viendo páginas protegidas después de cerrar sesión.
        $response = Inertia::location(route('login'));
        $response->headers->set('Clear-Site-Data', '"cache", "cookies", "storage"');

        return $response;
    }
}
