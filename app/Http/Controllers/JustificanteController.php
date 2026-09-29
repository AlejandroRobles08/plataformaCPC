<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class JustificanteController extends Controller
{
    // Vista para el integrante
    public function integrante(Consejo $consejo) {
        $integrante = Integrante::where('correo', auth()->user()->email)
            ->where('consejo_id', $consejo->id)
            ->firstOrFail();

        return Inertia::render('Asistencia/Justificantes/Integrante', [
            'consejo' => $consejo,
            'integrante' => $integrante,
        ]);
    }

    // Guardar justificante
    public function store(Request $request, Consejo $consejo) {
        // Obtener integrante autenticado perteneciente al consejo
        $integrante = Integrante::where('correo', auth()->user()->email)
            ->where('consejo_id', $consejo->id)
            ->firstOrFail();

        // Validar formulario
        $data = $request->validate([
            'fecha' => 'required|date',
            'tipo_sesion' => 'required|in:ordinaria,solemne,extraordinaria',
            'justificante' => 'required|file|mimes:pdf|max:4096',
        ]);

        // Buscar la sesión programada
        $sesion = Sesion::where('consejo_id', $consejo->id)
            ->whereDate('fecha', $data['fecha'])
            ->where('tipo_sesion', $data['tipo_sesion'])
            ->first();

        // Validar que la sesión exista
        if (!$sesion) {
            return back()->withErrors([
                'fecha' => 'La fecha seleccionada no pertenece a ninguna sesión programada.',
            ]);
        }

        // Buscar la asistencia mediante la sesión
        $asistencia = Asistencia::where('integrante_id', $integrante->id)
            ->where('sesion_id', $sesion->id)
            ->first();

        // Compatibilidad con asistencias creadas antes de usar sesion_id
        if (!$asistencia) {
            $asistencia = Asistencia::where('integrante_id', $integrante->id)
                ->whereDate('fecha', $data['fecha'])
                ->where('tipo_sesion', $data['tipo_sesion'])
                ->first();

            // Sincronizar la sesión en registros antiguos
            if ($asistencia && !$asistencia->sesion_id) {
                $asistencia->sesion_id = $sesion->id;
                $asistencia->save();
            }
        }

        // Validar que exista la asistencia
        if (!$asistencia) {
            return back()->withErrors([
                'fecha' => 'Existe la sesión, pero no se encontró un registro de asistencia para este integrante.',
            ]);
        }

        // *Validar que la asistencia sea una falta*
        if ($asistencia->estado === 'asistio') {
            return back()->withErrors([
                'fecha' => 'No puedes subir un justificante para una sesión en la que ya apareces como asistente.',
            ]);
        }

        // Eliminar justificante anterior si existe
        if ($asistencia->justificante) {
            Storage::disk('public')->delete($asistencia->justificante);
        }

        // Guardar nuevo archivo PDF
        $path = $request->file('justificante')
            ->store('justificantes', 'public');

        // Validar que el archivo se haya guardado correctamente
        if ($path === false) {
            return back()->withErrors([
                'justificante' => 'No fue posible guardar el archivo. Intenta nuevamente.',
            ]);
        }

        // Actualizar justificante y dejarlo pendiente de revisión
        $asistencia->update([
            'justificante' => $path,
            'estado_justificante' => 'pendiente',
        ]);

        return back()->with(
            'success',
            'Justificante enviado correctamente y pendiente de revisión.'
        );
    }

    // Vista administrativa
    public function admin(Request $request, Consejo $consejo) {
        // Integrantes disponibles para el filtro
        $integrantes = Integrante::where('consejo_id', $consejo->id)
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        // Obtener justificantes del consejo
        $justificantes = Asistencia::with([
            'integrante',
            'sesion',
        ])
            ->whereHas('integrante', fn ($query) =>
                $query->where('consejo_id', $consejo->id))
            ->whereNotNull('justificante')
            ->when(
                $request->integrante_id,
                fn ($query, $integranteId) =>
                    $query->where('integrante_id', $integranteId)
            )
            ->orderBy('fecha', 'desc')
            ->get();

        return Inertia::render('Asistencia/Justificantes/Admin', [
            'consejo' => $consejo,
            'integrantes' => $integrantes,
            'justificantes' => $justificantes,
            'filtros' => [
                'integrante_id' => $request->integrante_id,
            ],
        ]);
    }

    // Aprobar justificante
    public function aprobar(Asistencia $asistencia) {
        // Validar que exista un justificante
        if (!$asistencia->justificante) {
            return back()->withErrors([
                'justificante' => 'Esta asistencia no tiene un justificante para aprobar.',
            ]);
        }

        // Aprobar justificante y marcar la falta como justificada
        $asistencia->update([
            'estado_justificante' => 'aprobado',
            'estado' => 'justificada',
        ]);

        return back()->with(
            'success',
            'Justificante aprobado. La asistencia fue marcada como justificada.'
        );
    }


    // Rechazar justificante
    public function rechazar(Asistencia $asistencia) {
        // Validar que exista un justificante
        if (!$asistencia->justificante) {
            return back()->withErrors([
                'justificante' => 'Esta asistencia no tiene un justificante para rechazar.',
            ]);
        }

        // Rechazar justificante y mantener la falta
        $asistencia->update([
            'estado_justificante' => 'rechazado',
            'estado' => 'falto',
        ]);

        return back()->with(
            'success',
            'Justificante rechazado. La asistencia permanece como falta.'
        );
    }

    //visualizar justificantes en PDF
    public function show(Asistencia $asistencia) {
        //validar que exista el justificante*
        if (!$asistencia->justificante) {
            abort(404, 'No existe un justificante para esta asistencia.');
        }

        //Validar que el archivo exista físicamente*
        if (!Storage::disk('public')->exists($asistencia->justificante)) {
            abort(404, 'El archivo del justificante no fue encontrado.');
        }

        //mostrar PDF en el navegador*
        return response()->file(
            Storage::disk('public')->path($asistencia->justificante),
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }
}