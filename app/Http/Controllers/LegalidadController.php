<?php
namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Legalidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;

/** Periodo en el cargo de los integrantes y flujo de reelección. */
class LegalidadController extends Controller
{
    /** Lista los periodos del consejo; un integrante solo ve el suyo. */
    public function index(Consejo $consejo)
    {
        Gate::authorize('viewAny', Legalidad::class);$usuario = Auth::user();
        if ($usuario->hasRole('integrante')) {$integrante = Integrante::where(
                'user_id',$usuario->id)->first();

            // Debe tener integrante asociado y pertenecer a este consejo
            abort_unless($integrante, 403);
            abort_unless((int) $integrante->consejo_id === (int) $consejo->id,
                403);

            $integrantes = collect([$integrante]);
            $registros = Legalidad::with('integrante')
                ->where('consejo_id', $consejo->id)
                ->where('integrante_id', $integrante->id)->get();
        } else {
            $integrantes = Integrante::where('consejo_id',$consejo->id)->get();
            $registros = Legalidad::with('integrante')
                ->where('consejo_id', $consejo->id)->get();
        }

        return Inertia::render('Legalidad/Index', [
            'consejo' => $consejo,
            'integrantes' => $integrantes,
            'registros' => $registros,
        ]);
    }

    /** Estatus de reelección del consejo, solo para administradores. */
    public function estatus(Consejo $consejo)
    {
        Gate::authorize('viewAny', Legalidad::class);
        abort_unless(
            Auth::user()->hasRole(['admin', 'super_admin']),
            403
        );
        return Inertia::render('Legalidad/Estatus', [
            'consejo' => $consejo,
            'registros' => Legalidad::with('integrante')
                ->where('consejo_id', $consejo->id)->get(),
        ]);
    }

    /** Registra el periodo en el cargo de un integrante del consejo. */
    public function store(Request $request, Consejo $consejo)
    {
        Gate::authorize('create', Legalidad::class);
        // El integrante debe pertenecer al consejo de la ruta
        $validated = $request->validate([
            'integrante_id' => ['required',
                Rule::exists('integrantes', 'id')
                    ->where('consejo_id', $consejo->id),
            ],
            'inicio_cargo' => 'required|date',
            'fin_cargo' => 'required|date|after_or_equal:inicio_cargo',
        ]);

        // Duración del periodo en formato "AA:MM:DD"
        $inicio = Carbon::parse($validated['inicio_cargo']);
        $fin = Carbon::parse($validated['fin_cargo']);
        $periodo = $inicio->diff($fin);
        $periodoHabil = sprintf('%02d:%02d:%02d',
            $periodo->y,
            $periodo->m,
            $periodo->d);

        Legalidad::create([
            'consejo_id' => $consejo->id,
            'integrante_id' => $validated['integrante_id'],
            'inicio_cargo' => $validated['inicio_cargo'],
            'fin_cargo' => $validated['fin_cargo'],
            'periodo_habil' => $periodoHabil,
            'estatus_reeleccion' => 'pendiente',
            'ya_reelegido' => false,
        ]);

        return back()->with(
            'success',
            'Periodo registrado correctamente.'
        );
    }

    /** Envía la solicitud de reelección con sus PDFs, reemplazando los anteriores. */
    public function solicitarReeleccion(Request $request, Legalidad $legalidad)
    {
        Gate::authorize('solicitarReeleccion', $legalidad);

        // Solo se permite una reelección por integrante
        abort_if($legalidad->ya_reelegido,
            403,
            'Este integrante ya fue reelegido una vez.'
        );

        // "pendiente" con fecha de inicio = ya hay una solicitud en curso
        abort_if($legalidad->estatus_reeleccion === 'pendiente'
                && $legalidad->fecha_inicio_reeleccion !== null,
            403,
            'Ya existe una solicitud pendiente.'
        );

        $request->validate([
            'doc_nombramiento' => 'required|file|mimes:pdf|max:5120',
            'doc_carta_reeleccion' => 'required|file|mimes:pdf|max:5120',
            'doc_otros' => 'nullable|file|mimes:pdf|max:5120',
        ]);

        // Rutas actuales, se borran solo si la actualización tiene éxito
        $archivosAnteriores = [
            $legalidad->doc_nombramiento,
            $legalidad->doc_carta_reeleccion,
            $legalidad->doc_otros,
        ];

        $nuevosArchivos = [];
        try {
            $nuevosArchivos['doc_nombramiento'] =
                $request->file('doc_nombramiento')
                    ->store('legalidad/documentos', 'public');

            $nuevosArchivos['doc_carta_reeleccion'] =
                $request->file('doc_carta_reeleccion')
                    ->store('legalidad/documentos', 'public');

            $nuevosArchivos['doc_otros'] =
                $request->hasFile('doc_otros')
                    ? $request->file('doc_otros')
                        ->store('legalidad/documentos', 'public')
                    : null;

            // Reinicia la validación de cualquier solicitud anterior
            $legalidad->update([
                'doc_nombramiento' =>
                    $nuevosArchivos['doc_nombramiento'],
                'doc_carta_reeleccion' =>
                    $nuevosArchivos['doc_carta_reeleccion'],
                'doc_otros' => $nuevosArchivos['doc_otros'],
                'fecha_inicio_reeleccion' => now(),
                'estatus_reeleccion' => 'pendiente',
                'fecha_validacion' => null,
                'validado_por' => null,
            ]);
        } catch (\Throwable $e) {
            // Si falla, borra los archivos recién subidos
            foreach ($nuevosArchivos as $archivo) {
                if ($archivo) {
                    Storage::disk('public')->delete($archivo);
                }
            }
            throw $e;
        }

        // Borra los documentos que fueron reemplazados
        foreach ($archivosAnteriores as $archivo) {
            if (
                $archivo &&
                !in_array($archivo, $nuevosArchivos, true) &&
                Storage::disk('public')->exists($archivo)
            ) {
                Storage::disk('public')->delete($archivo);
            }
        }

        return back()->with(
            'success',
            'Solicitud enviada para validación.'
        );
    }

    /** Aprueba la reelección y reinicia el periodo por 3 años desde hoy. */
    public function aprobarReeleccion(Legalidad $legalidad)
    {
        Gate::authorize('validarReeleccion', $legalidad);

        abort_if($legalidad->ya_reelegido,
            403,
            'Este integrante ya fue reelegido una vez.'
        );

        abort_unless(
            $legalidad->estatus_reeleccion === 'pendiente' &&
            $legalidad->fecha_inicio_reeleccion !== null,
            403,
            'No existe una solicitud pendiente por validar.'
        );
        $inicio = Carbon::now();
        $fin = $inicio->copy()->addYears(3);

        // ya_reelegido impide una segunda reelección
        $legalidad->update([
            'inicio_cargo' => $inicio,
            'fin_cargo' => $fin,
            'periodo_habil' => '03:00:00',
            'estatus_reeleccion' => 'aprobado',
            'fecha_validacion' => now(),
            'validado_por' => Auth::id(),
            'ya_reelegido' => true,
        ]);

        return back()->with(
            'success',
            'Reelección aprobada y periodo reiniciado.'
        );
    }

    /** Rechaza la solicitud pendiente; el integrante puede volver a solicitarla. */
    public function rechazarReeleccion(Legalidad $legalidad)
    {
        Gate::authorize('rechazarReeleccion', $legalidad);
        abort_unless(
            $legalidad->estatus_reeleccion === 'pendiente' &&
            $legalidad->fecha_inicio_reeleccion !== null,
            403,
            'No existe una solicitud pendiente por validar.'
        );

        $legalidad->update([
            'estatus_reeleccion' => 'rechazado',
            'fecha_validacion' => now(),
            'validado_por' => Auth::id(),
        ]);

        return back()->with(
            'warning',
            'Solicitud de reelección rechazada.'
        );
    }
}
