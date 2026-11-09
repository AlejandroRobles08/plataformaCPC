<?php

namespace App\Http\Middleware;

use App\Support\CuentaDesactivada;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

// Si a alguien lo dan de baja con la sesión abierta, en su siguiente petición se cierra
// la sesión y se le muestra el aviso de cuenta desactivada (el login ya lo impide para sesiones nuevas).
class EnsureCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $baja = $request->user()?->bajaVigente();

        if (! $baja) {
            return $next($request);
        }

        CuentaDesactivada::cerrarSesion($request, $baja);

        // Navegación completa: así "atrás" no restaura desde history.state páginas que ya no puede ver
        return Inertia::location(route('cuenta.desactivada'));
    }
}
