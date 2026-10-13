<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Consejo;
use App\Models\Convocatoria;
use App\Models\Integrante;
use App\Models\Asistencia;
use App\Models\IntegranteBaja;

class ReporteController extends Controller
{
    //lista de consejos para elegir el reporte
    public function index()
    {
        $consejos = Consejo::orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('Reportes/IndexConsejos', [
            'consejos' => $consejos,
        ]);
    }

    //Reporte detallado de un consejo específico
    public function show(Consejo $consejo)
    {
        // ================= CONVOCATORIAS =================
        $convocatorias = Convocatoria::where('consejo_id', $consejo->id)->get();

        // ================= ASISTENCIAS (CORREGIDO) =================
        $asistencias = Asistencia::whereHas('integrante', function ($q) use ($consejo) {
                $q->where('consejo_id', $consejo->id);
            })
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

        // Integrantes
        $integrantes = $consejo->integrantes()
            ->with('documentos:id,integrante_id,tipo,archivo')
            ->get(['id', 'nombre', 'apellido_paterno', 'apellido_materno']);

        // Bajas
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

        // Reporte de asistencias por integrante
        $reporteAsistencias = $integrantes->map(function ($integrante) use ($asistencias) {

            $asistenciasIntegrante = $asistencias
                ->where('integrante_id', $integrante->id);

            $ordinarias = $asistenciasIntegrante
                ->where('tipo_sesion', 'ordinaria')
                ->where('estado', 'asistio')
                ->count();

            $extraordinarias = $asistenciasIntegrante
                ->where('tipo_sesion', 'extraordinaria')
                ->where('estado', 'asistio')
                ->count();

            $solemnes = $asistenciasIntegrante
                ->where('tipo_sesion', 'solemne')
                ->where('estado', 'asistio')
                ->count();

            return [
                'integrante' => $integrante->nombre . ' ' . $integrante->apellido,
                'ordinaria' => $ordinarias,
                'extraordinaria' => $extraordinarias,
                'solemne' => $solemnes,
                'total' => $ordinarias + $extraordinarias + $solemnes,
            ];
        })->values();

        //validacion de datos para mostrar mensaje de "no hay datos" en la vista
        $hayDatos =
            $convocatorias->isNotEmpty() ||
            $asistencias->isNotEmpty() ||
            $integrantes->isNotEmpty() ||
            $bajas->isNotEmpty();

        return Inertia::render('Reportes/Index', [
            'consejo' => $consejo,
            'convocatorias' => $convocatorias,
            'asistencias' => $asistencias,
            'integrantes' => $integrantes,
            'bajas' => $bajas,
            'reporteAsistencias' => $reporteAsistencias,
            'hayDatos' => $hayDatos,
        ]);
    }
}
