<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistencia;
use App\Models\Consejo;
use App\Models\Docu;
use App\Models\Legalidad;
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

        // Aviso rojo en la tarjeta: pendientes por revisar en el módulo, solo para quien los valida
        $pendientes = $user->hasAnyRole(['admin', 'super_admin'])
            ? $this->pendientesPorConsejo($origen)
            : collect();

        $consejos->each(fn ($consejo) => $consejo->setAttribute('pendientes', (int) ($pendientes[$consejo->id] ?? 0)));

        //este es el origen de la vista de los consejos
        return inertia('Consejos/Index', [
            'consejos' => $consejos,
            'origen' => $origen,
        ]);
    }

    // Cantidad de elementos por revisar en cada consejo, según el módulo:
    // Archivo digital: documentos subidos en espera de validación.
    // Asistencias: justificantes enviados en espera de validación.
    // Periodo en el cargo: solicitudes de reelección enviadas en espera de validación.
    private function pendientesPorConsejo(string $origen): \Illuminate\Support\Collection
    {
        $query = match ($origen) {
            'integrantes' => Docu::query()
                ->join('integrantes', 'integrantes.id', '=', 'docus.integrante_id')
                ->where('docus.estatus', Docu::PENDIENTE),
            'asistencias' => Asistencia::query()
                ->join('integrantes', 'integrantes.id', '=', 'asistencias.integrante_id')
                ->whereNotNull('asistencias.justificante')
                ->where('asistencias.estado_justificante', 'pendiente'),
            // El periodo guarda su propio consejo_id (así filtra la vista de Periodo en el cargo)
            'legalidad' => Legalidad::query()
                ->where('estatus_reeleccion', 'pendiente')
                ->whereNotNull('fecha_inicio_reeleccion'),
            default => null,
        };

        if (! $query) {
            return collect();
        }

        $columna = $origen === 'legalidad' ? 'legalidad.consejo_id' : 'integrantes.consejo_id';

        return $query->selectRaw("{$columna} as consejo_id, COUNT(*) as total")
            ->groupBy($columna)
            ->pluck('total', 'consejo_id');
    }

    //formulario para dar de alta e instalar un consejo
    public function create(Request $request)
    {
        $origen = $request->query('origen');

        return inertia('Consejos/Instalacion', [
            'origen' => array_key_exists($origen, self::RUTAS_ORIGEN) ? $origen : 'integrantes',
            'iconos' => Consejo::ICONOS,
        ]);
    }

    //el consejo nace sin instalar; se instala al completar el mínimo de fórmulas
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:consejos,nombre',
            'descripcion' => 'nullable|string|max:255',
            'icono' => ['required', Rule::in(array_keys(Consejo::ICONOS))],
            'origen' => ['nullable', Rule::in(array_keys(self::RUTAS_ORIGEN))],
        ], [
            'icono.required' => 'Selecciona un ícono para el consejo.',
        ]);

        Consejo::create([
            'nombre' => trim($validated['nombre']),
            'descripcion' => $validated['descripcion'] ?? null,
            'icono' => $validated['icono'],
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
