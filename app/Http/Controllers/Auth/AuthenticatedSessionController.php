<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

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

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withHeaders([
                'Clear-Site-Data' => '"cache", "cookies", "storage"',
            ]);
    }
}
