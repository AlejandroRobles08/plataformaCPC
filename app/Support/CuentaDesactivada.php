<?php

namespace App\Support;

use App\Models\IntegranteBaja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Cierra la sesión de una persona dada de baja y deja en la sesión nueva (flash) los datos
// del aviso. Van en sesión y no en la URL para que nadie pueda consultar la baja de otra persona.
class CuentaDesactivada
{
    public const FLASH = 'cuenta_desactivada';

    public static function cerrarSesion(Request $request, IntegranteBaja $baja): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->flash(self::FLASH, $baja->datosAviso());
    }
}
