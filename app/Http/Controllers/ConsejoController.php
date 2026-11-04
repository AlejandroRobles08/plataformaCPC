<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Consejo;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ConsejoController extends Controller
{
    // Vista de tarjetas a la que se regresa según el módulo de origen
    private const RUTAS_ORIGEN = [
        'integrantes' => 'consejos.index',
        'asistencias' => 'consejos.asistencias',
        'convocatorias' => 'consejos.convocatorias',
        'reportes' => 'consejos.reportes',
        'legalidad' => 'consejos.legalidad',
    ];

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

        // Los administradores ven todos los consejos; el resto de usuarios solo el suyo
        $user = request()->user();
        $consejos = Consejo::query()
            ->unless(
                $user->hasAnyRole(['admin', 'super_admin']),
                fn ($query) => $query->whereKey($user->integrante?->consejo_id)
            )
            ->orderBy('id')
            ->get();

        //este es el origen de la vista de los consejos
        return inertia('Consejos/Index', [
            'consejos' => $consejos,
            'origen' => $origen,
        ]);
    }

    //formulario para dar de alta e instalar un consejo
    public function create(Request $request)
    {
        $origen = $request->query('origen');

        return inertia('Consejos/Instalacion', [
            'origen' => array_key_exists($origen, self::RUTAS_ORIGEN) ? $origen : 'integrantes',
        ]);
    }

    //el consejo nace sin instalar; se instala al completar el mínimo de fórmulas
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:consejos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'origen' => ['nullable', Rule::in(array_keys(self::RUTAS_ORIGEN))],
        ]);

        Consejo::create([
            'nombre' => trim($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        return redirect()->route(self::RUTAS_ORIGEN[$validated['origen'] ?? 'integrantes']);
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
