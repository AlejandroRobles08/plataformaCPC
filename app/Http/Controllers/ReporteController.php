<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\Gate;
use App\Models\Consejo;
use App\Models\Convocatoria;
use App\Models\Integrante;
use App\Models\Asistencia;
use App\Models\IntegranteBaja;

class ReporteController extends Controller
{
    //Menú de reportes disponibles para un consejo
    public function show(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        //conteos para mostrar un resumen en cada tarjeta del menú
        $totales = [
            'sesiones' => Convocatoria::where('consejo_id', $consejo->id)->count(),
            'asistencias' => $this->asistenciasQuery($consejo)->count(),
            'documentos' => $consejo->integrantes()->count(),
            'bajas' => IntegranteBaja::where('consejo_id', $consejo->id)->count(),
        ];

        return Inertia::render('Reportes/Index', [
            'consejo' => $consejo,
            'totales' => $totales,
            //validacion de datos para mostrar mensaje de "no hay datos" en la vista
            'hayDatos' => array_sum($totales) > 0,
        ]);
    }

    //Reporte de sesiones convocadas y realizadas
    public function sesiones(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        $convocatorias = Convocatoria::where('consejo_id', $consejo->id)->get();

        return Inertia::render('Reportes/Sesiones', [
            'consejo' => $consejo,
            'convocatorias' => $convocatorias,
        ]);
    }

    //Reporte de asistencias por integrante
    public function asistencias(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        $asistencias = $this->asistenciasQuery($consejo)
            ->with('integrante:id,nombre,apellido_paterno,apellido_materno,puesto') // Carga solo los campos necesarios del integrante
            ->get([
                'id',
                'integrante_id',
                'tipo_sesion',
                'estado',
                'fecha',
                'evidencia'
            ])
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'integrante_id' => $a->integrante_id,
                    'integrante' => $a->integrante,
                    'fecha' => $a->fecha,
                    'tipo_sesion' => $a->tipo_sesion,
                    'estado' => $a->estado,

                    //Persistencia de simbología sin perder los datos originales
                    'simbolo' => match ($a->estado) {
                        'asistio' => 'A',
                        'justificada' => 'IJ',
                        default => 'I',
                    },
                ];
            });

        return Inertia::render('Reportes/Asistencias', [
            'consejo' => $consejo,
            'asistencias' => $asistencias,
        ]);
    }

    //Checklist de documentos por integrante
    public function documentos(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        $integrantes = $consejo->integrantes()
            ->with('documentos:id,integrante_id,tipo,archivo')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno']);

        return Inertia::render('Reportes/Documentos', [
            'consejo' => $consejo,
            'integrantes' => $integrantes,
        ]);
    }

    //Integrantes dados de baja
    public function bajas(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        $bajas = IntegranteBaja::where('consejo_id', $consejo->id)
            ->orderBy('fecha_baja', 'desc')
            ->get([
                'id',
                'integrante_id',
                'nombre',
                'apellido',
                'motivo',
                'fecha_baja',
                'evidencia_pdf',
            ]);

        return Inertia::render('Reportes/Bajas', [
            'consejo' => $consejo,
            'bajas' => $bajas,
        ]);
    }

    //Asistencias de los integrantes que pertenecen al consejo
    private function asistenciasQuery(Consejo $consejo)
    {
        return Asistencia::whereHas('integrante', function ($q) use ($consejo) {
            $q->where('consejo_id', $consejo->id);
        });
    }
}
