<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Consejo;
use App\Models\Convocatoria;
use Illuminate\Support\Facades\Storage;

class ConvocatoriaController extends Controller
{
    // Campos de la tabla que guardan la ruta de un documento PDF
    private const CAMPOS_DOCUMENTO = [
        'convocatoria_documento',
        'documento',
        'lista_asistencia',
        'evidencia',
    ];

    public function index(Request $request, Consejo $consejo)
    {
        $filtros = $request->validate([
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
        ]);

        $convocatorias = $consejo->convocatorias()
            ->when($filtros['fecha_desde'] ?? null, fn ($q, $desde) => $q->whereDate('fecha', '>=', $desde))
            ->when($filtros['fecha_hasta'] ?? null, fn ($q, $hasta) => $q->whereDate('fecha', '<=', $hasta))
            ->orderBy('fecha', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Convocatoria/Index', [
            'consejo' => $consejo,
            'convocatorias' => $convocatorias,
            'filtros' => [
                'fecha_desde' => $filtros['fecha_desde'] ?? '',
                'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
            ],
        ]);
    }

    // Visualizar en el navegador un documento de la convocatoria (requiere convocatorias.ver)
    public function documento(Request $request, Convocatoria $convocatoria, string $campo)
    {
        abort_unless(in_array($campo, self::CAMPOS_DOCUMENTO, true), 404);

        // Administración ve todos los consejos; el resto solo el suyo
        $user = $request->user();
        abort_unless(
            $user->hasAnyRole(['admin', 'super_admin'])
                || $user->integrante?->consejo_id === $convocatoria->consejo_id,
            403
        );

        $ruta = $convocatoria->{$campo};
        $disco = Storage::disk('public');

        abort_unless($ruta && $disco->exists($ruta), 404, 'El archivo no se encuentra disponible.');

        return response()->file($disco->path($ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $campo . '_' . $convocatoria->fecha . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, Consejo $consejo)
    {
        //Validación base
        $data = $request->validate([
            'tipo_sesion' => 'required|in:ordinaria,solemne,extraordinaria',
            'fecha' => 'required|date',

            // Archivos
            'convocatoria_documento' => 'nullable|file|mimes:pdf|max:4096',
            'documento' => 'nullable|file|mimes:pdf|max:4096',
            'lista_asistencia' => 'nullable|file|mimes:pdf|max:4096',
            'evidencia' => 'nullable|file|mimes:pdf|max:4096',

            // Estados
            'estado_convocatoria' => 'nullable|boolean',
            'estado_sesion' => 'nullable|boolean',
        ]);

        //Normalizar booleanos
        $data['estado_convocatoria'] = $data['estado_convocatoria'] ?? false;
        $data['estado_sesion'] = $data['estado_sesion'] ?? false;

        //Regla de negocio: no sesión sin convocatoria
        if ($data['estado_sesion'] && !$data['estado_convocatoria']) {
            return back()->withErrors([
                'estado_sesion' => 'No se puede registrar una sesión sin convocatoria.',
            ]);
        }

        //Guardar archivos si existen
        if ($request->hasFile('convocatoria_documento')) {
            $data['convocatoria_documento'] = $request->file('convocatoria_documento')
                ->store('convocatorias/convocatorias', 'public');
        }

        if ($request->hasFile('documento')) {
            $data['documento'] = $request->file('documento')
                ->store('convocatorias/documentos', 'public');
        }

        if ($request->hasFile('lista_asistencia')) {
            $data['lista_asistencia'] = $request->file('lista_asistencia')
                ->store('convocatorias/listas', 'public');
        }

        if ($request->hasFile('evidencia')) {
            $data['evidencia'] = $request->file('evidencia')
                ->store('convocatorias/evidencias', 'public');
        }

        //Relación con consejo
        $data['consejo_id'] = $consejo->id;

        //Crear convocatoria
        Convocatoria::create($data);

        return back()->with('success', 'Convocatoria guardada correctamente.');
    }
}
