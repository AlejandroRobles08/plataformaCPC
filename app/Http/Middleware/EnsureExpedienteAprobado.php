<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Mientras el expediente del Archivo digital no esté completo y aprobado,
// el integrante solo puede subir y consultar sus documentos: los demás módulos quedan bloqueados.
class EnsureExpedienteAprobado
{
    // Rutas permitidas con el expediente pendiente
    private const PERMITIDAS = [
        'consejos.index',
        'consejos.integrantes',
        'integrantes.alta',
        'docu.*',
        'profile.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $integrante = $user?->integrante;

        if (
            $integrante
            && $user->hasRole('integrante')
            && ! $request->routeIs(...self::PERMITIDAS)
            && ! $integrante->expedienteAprobado()
        ) {
            return redirect()
                ->route('docu.index', $integrante->id)
                ->with('warning', 'Para usar los demás módulos, todos tus documentos del Archivo digital deben estar subidos y aprobados.');
        }

        return $next($request);
    }
}
