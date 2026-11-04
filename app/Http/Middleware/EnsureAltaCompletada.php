<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Mientras un integrante no termine "Darme de alta", solo puede usar el Archivo digital
// para capturar sus datos: documentos y demás módulos quedan bloqueados.
class EnsureAltaCompletada
{
    // Rutas permitidas con el alta pendiente
    private const PERMITIDAS = [
        'consejos.index',
        'consejos.integrantes',
        'integrantes.alta',
        'profile.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $integrante = $request->user()?->integrante;

        if (
            $integrante
            && $integrante->alta_completada_at === null
            && $request->user()->hasRole('integrante')
            && ! $request->routeIs(...self::PERMITIDAS)
        ) {
            return redirect()
                ->route('consejos.integrantes', $integrante->consejo_id)
                ->with('warning', 'Primero completa tu alta con el botón "Darme de alta".');
        }

        return $next($request);
    }
}
