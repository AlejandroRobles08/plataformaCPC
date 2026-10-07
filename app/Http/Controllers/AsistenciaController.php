<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Asistencia;
use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AsistenciaController extends Controller
{
    // Vista principal de asistencias
    public function index(Consejo $consejo)
    {
        Gate::authorize('viewAny', [Integrante::class, $consejo]);
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $integranteActual = null;

        if($user->hasRole('integrante')){
            $integranteActual = $user->integrante;
            //seguridad adicional: el integrante debe pertenecer al consejo consultado
            if(!$integranteActual || $integranteActual->consejo_id !== $consejo->id){
                abort(403, 'No tienes permiso para acceder a este consejo.');
            }
            //el integrante solamente ver a los integrantes de su formula
        $integrantes = Integrante::where('consejo_id', $consejo->id)
            ->where('formula', $integranteActual?->formula)->orderBy('nombre')->get();

            //IMPORTANTE: Aunque pueda ver el nombre de su compañero,NO se envian sus asistencias al navegador.
            $isDatos = collect([$integranteActual->id]);
        } else {
            //los admins pueden ver todos los integrantes del consejo
            $integrantes = Integrante::where('consejo_id', $consejo->id)->orderBy('nombre')->get();
            $isDatos = $integrantes->pluck('id');
        }

        $asistencias = Asistencia::whereIn('integrante_id', $isDatos)->get();
        $justificantes = Asistencia::with(['integrante', 'sesion'])
            ->whereIn('integrante_id', $isDatos)
            ->whereNotNull('justificante')->orderBy('fecha', 'desc')
            ->get();
        
        return Inertia::render('Asistencia/Index', [
            'consejo' => $consejo,
            'integrantes' => $integrantes,
            'asistencias' => $asistencias,
            'justificantes' => $justificantes,
            'integranteActualId' => $integranteActual?->id,
        ]);
    }

    // Vista para crear asistencias
    public function create(Consejo $consejo, Request $request)
    {
        return Inertia::render('Asistencia/Form', [
            'consejo' => $consejo,
            'fecha' => $request->fecha,
            'integrantes' => Integrante::where('consejo_id', $consejo->id)->get(),
        ]);
    }

    // Calendario con sesiones programadas
    public function calendar(Consejo $consejo)
    {
        $integrantes = Integrante::where('consejo_id', $consejo->id)->get();

        $sesiones = Sesion::where('consejo_id', $consejo->id)
            ->orderBy('fecha')
            ->get();
        $asistencias = Asistencia::whereIn( 'integrante_id', $integrantes->pluck('id')
            )->whereIn('sesion_id', $sesiones->pluck('id'))
            ->get();

        $integrante = null;

        if (auth()->check() && auth()->user()->hasRole('integrante')) {
            $integrante = Integrante::where('correo', auth()->user()->email)
                ->where('consejo_id', $consejo->id)->first();
        }

        return Inertia::render('Asistencia/Calendar', [
            'consejo' => $consejo,
            'sesiones' => $sesiones,
            'integrantes' => $integrantes,
            'integrante' => $integrante,
            'asistencias' => $asistencias,
        ]);
    }

    // Programar una nueva sesión
    public function storeProgramacion(Request $request, Consejo $consejo)
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'tipo_sesion' => 'required|in:ordinaria,solemne,extraordinaria',
        ]);

        $existe = Sesion::where('consejo_id', $consejo->id)
            ->whereDate('fecha', $validated['fecha'])
            ->where('tipo_sesion', $validated['tipo_sesion'])
            ->exists();

        if ($existe) {
            return back()->withErrors([
                'fecha' => 'Ya existe una sesión de este tipo programada para esta fecha.',
            ]);
        }

        Sesion::create([
            'consejo_id' => $consejo->id,
            'fecha' => $validated['fecha'],
            'tipo_sesion' => $validated['tipo_sesion'],
            'estado' => 'programada',
        ]);

        return back()->with('success', 'Sesión programada correctamente.');
    }

    // Historial de asistencias
    public function history($consejoId, $integranteId)
    {
        $integrante = Integrante::where('id', $integranteId)
            ->where('consejo_id', $consejoId)
            ->firstOrFail();
        Gate::authorize('view', [Integrante::class, $integrante]);

        $historial = Asistencia::where('integrante_id', $integranteId)
            ->orderBy('fecha', 'desc')
            ->get([
                'id',
                'sesion_id',
                'fecha',
                'estado',
                'tipo_sesion',
                'evidencia',
                'justificante',
                'estado_justificante',
                'comentario_justificante',
            ]);
        return Inertia::render('Asistencia/History', [
            'integrante' => $integrante,
            'historial' => $historial,
        ]);
    }

    // Registrar asistencias desde el calendario
    public function storeSesion(Request $request, Consejo $consejo)
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'tipo_sesion' => 'required|in:ordinaria,solemne,extraordinaria',
            'asistencias' => 'required|array',
            'asistencias.*.integrante_id' => 'required|exists:integrantes,id',
            'asistencias.*.estado' => 'required|in:asistio,falto,justificada',
            'evidencia' => 'nullable|file|mimes:pdf|max:4096',
        ]);

        $sesion = Sesion::where('consejo_id', $consejo->id)
            ->whereDate('fecha', $validated['fecha'])
            ->where('tipo_sesion', $validated['tipo_sesion'])
            ->first();

        if (!$sesion) {
            return back()->withErrors([
                'fecha' => 'La fecha seleccionada no pertenece a ninguna sesión programada.',
            ]);
        }

        $evidenciaPath = null;

        if ($request->hasFile('evidencia')) {
            $evidenciaPath = $request->file('evidencia')
                ->store('evidencias', 'public');
        }

        foreach ($validated['asistencias'] as $item) {
            $perteneceAlConsejo = Integrante::where('id', $item['integrante_id'])
                ->where('consejo_id', $consejo->id)
                ->exists();

            if (!$perteneceAlConsejo) continue;

            Asistencia::updateOrCreate(
                [
                    'integrante_id' => $item['integrante_id'],
                    'sesion_id' => $sesion->id,
                ],
                [
                    'fecha' => $validated['fecha'],
                    'tipo_sesion' => $validated['tipo_sesion'],
                    'estado' => $item['estado'],
                    'evidencia' => $evidenciaPath,
                ]
            );
        }

        return back()->with(
            'success',
            'Asistencia registrada correctamente.'
        );
    }

    // Vista de evidencias documentales
    public function evidencias(Consejo $consejo)
    {
        $integrantes = Integrante::where('consejo_id', $consejo->id)
            ->pluck('id');

        $sesiones = Asistencia::whereIn('integrante_id', $integrantes)
            ->whereNotNull('evidencia')
            ->select('fecha', 'tipo_sesion', 'evidencia')
            ->groupBy('fecha', 'tipo_sesion', 'evidencia')
            ->orderBy('fecha', 'desc')
            ->get();

        return Inertia::render('Asistencia/Evidencia', [
            'consejo' => $consejo,
            'sesiones' => $sesiones,
        ]);
    }
}
