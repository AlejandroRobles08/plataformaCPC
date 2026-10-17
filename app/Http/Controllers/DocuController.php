<?php

namespace App\Http\Controllers;

use App\Models\Docu;
use App\Models\Integrante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DocuController extends Controller
{
    private const TAMANO_MAXIMO_KB = 2048;

    public function index(Integrante $integrante)
    {
        Gate::authorize('viewAny', [Docu::class, $integrante]);

        $integrante->load('documentos');

        return Inertia::render('Docu/Index', [
            'integrante' => $integrante,
            'flash' => session('success'),
        ]);
    }

    public function store(Request $request, Integrante $integrante)
    {
        Gate::authorize('create', [Docu::class, $integrante]);

        $reglas = [];
        foreach (Docu::TIPOS as $tipo) {
            $reglas[$tipo] = 'nullable|file|mimes:pdf|max:' . self::TAMANO_MAXIMO_KB;
        }
        $request->validate($reglas, [
            'mimes' => 'El documento debe ser un archivo PDF.',
            'max' => 'El documento no debe superar los ' . (self::TAMANO_MAXIMO_KB / 1024) . ' MB.',
        ]);

        $tiposRecibidos = array_values(array_filter(Docu::TIPOS, fn($tipo) => $request->hasFile($tipo)));

        if (empty($tiposRecibidos)) {
            throw ValidationException::withMessages([
                'documentos' => 'Selecciona al menos un documento para guardar.',
            ]);
        }

        $existentes = $integrante->documentos()
            ->whereIn('tipo', $tiposRecibidos)
            ->get()
            ->keyBy('tipo');

        // Un documento aprobado o en revisión no se puede reemplazar
        $bloqueados = $existentes->reject(fn(Docu $doc) => $doc->puedeReemplazarse());
        if ($bloqueados->isNotEmpty()) {
            throw ValidationException::withMessages(
                $bloqueados->mapWithKeys(fn(Docu $doc) => [
                    $doc->tipo => $doc->estatus === Docu::APROBADO
                        ? 'Este documento ya fue aprobado y no puede reemplazarse.'
                        : 'Este documento está en revisión; podrás reemplazarlo si es rechazado.',
                ])->all()
            );
        }

        $nuevos = [];
        $anteriores = [];

        try {
            DB::transaction(function () use ($request, $integrante, $tiposRecibidos, $existentes, &$nuevos, &$anteriores) {
                foreach ($tiposRecibidos as $tipo) {
                    $ruta = $request->file($tipo)->store(Docu::carpetaDe($integrante->id), Docu::DISCO);
                    if (! $ruta) {
                        throw new \RuntimeException("No fue posible guardar el documento {$tipo}.");
                    }
                    $nuevos[] = $ruta;

                    $datos = [
                        'archivo' => $ruta,
                        'estatus' => Docu::PENDIENTE,
                        'observacion' => null,
                        'validado_por' => null,
                        'validado_at' => null,
                    ];

                    if ($documento = $existentes->get($tipo)) {
                        // Al reemplazar un documento rechazado vuelve al flujo de validación
                        $anteriores[] = $documento->archivo;
                        $documento->update($datos);
                    } else {
                        $integrante->documentos()->create($datos + ['tipo' => $tipo]);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Si algo falla no deben quedar archivos huérfanos
            Docu::disco()->delete($nuevos);
            throw $e;
        }

        // Los archivos reemplazados se eliminan solo cuando la base de datos ya se actualizó
        Docu::disco()->delete($anteriores);

        return redirect()
            ->back()
            ->with('success', 'Documentos guardados correctamente.');
    }

    // Visualizar el PDF en el navegador
    public function show(Docu $documento)
    {
        Gate::authorize('view', $documento);

        abort_unless($documento->existeArchivo(), 404, 'El archivo no se encuentra disponible.');

        return response()->file(Docu::disco()->path($documento->archivo), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $documento->nombreDescarga() . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // Descarga de archivos
    public function download(Docu $documento)
    {
        Gate::authorize('view', $documento);

        abort_unless($documento->existeArchivo(), 404, 'El archivo no se encuentra disponible.');

        return Docu::disco()->download($documento->archivo, $documento->nombreDescarga(), [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // Aprobar documento
    public function aprobar(Docu $documento)
    {
        Gate::authorize('aprobar', $documento);

        if (! $documento->puedeValidarse()) {
            return redirect()->back()->with('error', 'El documento ya fue aprobado.');
        }

        $documento->update([
            'estatus' => Docu::APROBADO,
            'observacion' => null,
            'validado_por' => Auth::id(),
            'validado_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Documento aprobado correctamente.');
    }

    // Rechazar documento
    public function rechazar(Request $request, Docu $documento)
    {
        Gate::authorize('rechazar', $documento);

        $request->validate([
            'observacion' => ['required', 'string', 'max:2000'],
        ]);

        if (! $documento->puedeValidarse()) {
            return redirect()->back()->with('error', 'Un documento aprobado no puede rechazarse.');
        }

        $documento->update([
            'estatus' => Docu::RECHAZADO,
            'observacion' => $request->observacion,
            'validado_por' => Auth::id(),
            'validado_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Documento rechazado y observación registrada.');
    }
}
