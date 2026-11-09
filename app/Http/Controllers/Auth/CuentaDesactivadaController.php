<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\CuentaDesactivada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Aviso para quien intenta entrar con una cuenta dada de baja.
// Solo se llega aquí desde el login o al cerrar una sesión activa; sin esos datos se vuelve al login.
class CuentaDesactivadaController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $aviso = $request->session()->get(CuentaDesactivada::FLASH);

        if (! $aviso) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/CuentaDesactivada', $aviso);
    }
}
