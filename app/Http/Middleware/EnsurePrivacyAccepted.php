<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivacyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si no hay usuario autenticado,
        // dejamos que el middleware auth se encargue.
        if (!$user) {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | Rutas permitidas aunque no haya aceptado el aviso
        |--------------------------------------------------------------------------
        */

        if (
            $request->routeIs('logout')
        ) {
            $response = $next($request);

            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );

            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response; 
        }

        /*
        |--------------------------------------------------------------------------
        | Si no ha aceptado el aviso, cerrar sesión
        |--------------------------------------------------------------------------
        */

        if (!$user->privacy_accepted) {

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Navegación completa: ver AuthenticatedSessionController::destroy()
            $response = Inertia::location(route('login'));
            $response->headers->set('Clear-Site-Data', '"cache", "cookies", "storage"');

            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Usuario que ya aceptó
        |--------------------------------------------------------------------------
        */

        $response = $next($request);

        $response->headers->set(
            'Cache-Control',
            'no-store, no-cache, must-revalidate, max-age=0'
        );

        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}