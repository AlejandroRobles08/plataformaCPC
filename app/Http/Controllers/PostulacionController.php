<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Docu;
use App\Models\Integrante;
use App\Models\Legalidad;
use App\Models\Postulacion;
use App\Models\PostulacionDocumento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PostulacionController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['invitado', 'admin', 'super_admin'])) {
            abort(403, 'No tienes acceso al módulo de postulaciones.');
        }

        $query = Postulacion::with(['consejo', 'documentos', 'user'])->latest();

        if ($user->hasRole('invitado')) {
            $query->where('user_id', $user->id);
        }

        $formulasOcupadas = Integrante::select(
            'consejo_id',
            'formula',
            DB::raw('COUNT(*) as total')
        )
            ->whereNotNull('formula')
            ->whereBetween('formula', [1, 15])
            ->groupBy('consejo_id', 'formula')
            ->get()
            ->groupBy('consejo_id')
            ->map(fn ($formulas) => $formulas->map(fn ($formula) => [
                'formula' => (int) $formula->formula,
                'total' => (int) $formula->total,
            ])->values());

        return Inertia::render('Postulaciones/Index', [
            'postulaciones' => $query->get(),
            'consejos' => Consejo::orderBy('nombre')->get(),
            'formulasOcupadas' => $formulasOcupadas,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasRole('invitado')) {
            abort(403, 'No tienes permiso para crear una postulación.');
        }

        if ($user->postulacion()->exists()) {
            return redirect()
                ->route('postulaciones.index')
                ->with('error', 'Ya tienes una postulación registrada.');
        }

        $rules = [
            'nombre' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'genero' => 'required|in:mujer,hombre,no_binario,prefiero_autodescribirme,prefiero_no_responder',
            'direccion' => 'required|string|max:500',
            'correo' => 'required|email|max:255',
            'consejo_id' => 'required|exists:consejos,id',
            'puesto' => 'required|string|max:255',
            'formula' => 'required|integer|min:1|max:15',
        ];

        foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
            $rules["documentos.$tipo"] = 'required|file|mimes:pdf|max:2048';
        }

        $request->validate($rules);

        $total = Integrante::where('consejo_id', $request->consejo_id)
            ->where('formula', $request->formula)
            ->count();

        if ($total >= 2) {
            return back()->withErrors([
                'formula' => 'La fórmula seleccionada ya está completa.'
            ]);
        }

        DB::transaction(function () use ($request, $user) {
            $postulacion = Postulacion::create([
                'user_id' => $user->id,
                'nombre' => $request->nombre,
                'apellidos' => $request->apellidos,
                'genero'=> $request->genero,
                'direccion' => $request->direccion,
                'correo' => $request->correo,
                'consejo_id' => $request->consejo_id,
                'puesto' => $request->puesto,
                'formula' => $request->formula,
                'estatus' => 'pendiente',
                'fecha_postulacion' => now(),
            ]);

            foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
                $archivo = $request->file("documentos.$tipo");

                PostulacionDocumento::create([
                    'postulacion_id' => $postulacion->id,
                    'tipo' => $tipo,
                    'archivo' => $archivo->store('postulaciones', 'public'),
                ]);
            }
        });

        return redirect()
            ->route('postulaciones.index')
            ->with('success', 'Tu postulación fue creada correctamente y será enviada a validación.');
    }

    public function validacion()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para validar postulaciones.');
        }

        return Inertia::render('Postulaciones/Postulacion', [
            'postulaciones' => Postulacion::with([
                'consejo',
                'documentos',
                'user',
            ])
                ->where('estatus', 'pendiente')
                ->latest()
                ->get(),
        ]);
    }

    public function aprobar(Request $request, Postulacion $postulacion)
    {
    $user = Auth::user();

    if (! $user->hasAnyRole(['admin', 'super_admin'])) {
        abort(403, 'No tienes permiso para aprobar postulaciones.');
    }

    $request->validate([
        'fecha_validacion' => 'required|date',
        'acta_resolucion' => 'required|file|mimes:pdf|max:4096',
    ]);

    if (! $postulacion->user_id) {
        return redirect()
            ->route('postulaciones.validacion')
            ->with('error', 'Esta postulación no tiene un usuario asociado.');
    }

    return DB::transaction(function () use ($request, $postulacion, $user) {
        if ($postulacion->estatus !== 'pendiente') {
            abort(422, 'Esta postulación ya fue procesada.');
        }

        $total = Integrante::where('consejo_id', $postulacion->consejo_id)
            ->where('formula', $postulacion->formula)
            ->count();

        if ($total >= 2) {
            abort(422, 'La fórmula seleccionada ya está completa.');
        }

        $archivo = $request->file('acta_resolucion');

        $ruta = $archivo->storeAs(
            'resoluciones',
            'acta_' . $postulacion->id . '_' . time() . '.pdf',
            'public'
        );

        $integrante = Integrante::create([
            'user_id' => $postulacion->user_id,
            'nombre' => $postulacion->nombre,
            'apellido' => $postulacion->apellidos,
            'puesto' => $postulacion->puesto,
            'correo' => $postulacion->correo,
            'genero' => $postulacion->genero,
            'direccion' => $postulacion->direccion,
            'discapacidad' => null,
            'discapacidad_tipo' => null,
            'consejo_id' => $postulacion->consejo_id,
            'formula' => $postulacion->formula,
        ]);

        foreach ($postulacion->documentos as $doc) {
            Docu::create([
                'integrante_id' => $integrante->id,
                'tipo' => $doc->tipo,
                'archivo' => $doc->archivo,
            ]);
        }

        $fecha = Carbon::parse($request->fecha_validacion);

        Legalidad::create([
            'consejo_id' => $postulacion->consejo_id,
            'integrante_id' => $integrante->id,
            'inicio_cargo' => $fecha->format('Y-m-d'),
            'fin_cargo' => $fecha->copy()->addYears(3)->format('Y-m-d'),
            'periodo_habil' => '1',
            'estatus_reeleccion' => 'pendiente',
            'fecha_inicio_reeleccion' => null,
            'fecha_validacion' => $fecha->format('Y-m-d'),
            'validado_por' => $user->id,
            'ya_reelegido' => false,
        ]);

        $postulacion->update([
            'estatus' => 'aprobada',
            'validado_por' => $user->id,
            'fecha_validacion' => $fecha->format('Y-m-d'),
            'acta_resolucion' => $ruta,
        ]);
        //cambio de rol del usuario a integrante
        $postulante = $postulacion->user;
        if ($postulante) {
            $postulante->syncRoles(['integrante']);
        }

        return redirect()
            ->route('postulaciones.validacion')
            ->with('success', 'Postulación aprobada correctamente.');
     });
    }

    public function rechazar(Request $request, Postulacion $postulacion)
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para rechazar postulaciones.');
        }

        $request->validate([
            'fecha_validacion' => 'required|date',
            'acta_resolucion' => 'required|file|mimes:pdf|max:4096',
        ]);

        if ($postulacion->estatus !== 'pendiente') {
            return redirect()
                ->route('postulaciones.validacion')
                ->with('error', 'Esta postulación ya fue procesada.');
        }

        $archivo = $request->file('acta_resolucion');

        $ruta = $archivo->storeAs(
            'resoluciones',
            'acta_' . $postulacion->id . '_' . time() . '.pdf',
            'public'
        );

        $postulacion->update([
            'estatus' => 'no_aprobada',
            'validado_por' => $user->id,
            'fecha_validacion' => $request->fecha_validacion,
            'acta_resolucion' => $ruta,
        ]);

        return redirect()
            ->route('postulaciones.validacion')
            ->with('success', 'Postulación rechazada correctamente.');
    }
}