<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Consejo;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ConsejoController extends Controller
{
    //se reutiliza la vista de consejos para cada módulo
    public function index()
    {
        $origen = request()->routeIs('consejos.convocatorias')
            ? 'convocatorias'
            : (request()->routeIs('consejos.asistencias')
                ? 'asistencias'
                : (request()->routeIs('consejos.reportes')
                    ? 'reportes'
                    : (request()->routeIs('consejos.legalidad')
                        ? 'legalidad'
                        : 'integrantes')));

        $consejos = Consejo::orderBy('id')->get();

        //este es el origen de la vista de los consejos
        return inertia('Consejos/Index', [
            'consejos' => $consejos,
            'origen' => $origen,
        ]);
    }

    public function updateDescripcion(Request $request, Consejo $consejo){
        $validated = $request->validate([
            'descripcion' => 'required|string|max:2000',
        ]);
        $consejo->update([
            'descripcion' => $validated['descripcion'],
        ]);
        return redirect()->back();
    }
}
