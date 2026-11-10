<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Docu;
use App\Models\Integrante;
use App\Models\Legalidad;
use App\Models\Postulacion;
use App\Models\PostulacionDocumento;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PostulacionController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['invitado', 'admin', 'super_admin'])) {
            abort(403, 'No tienes acceso al módulo de postulaciones.');
        }

        $query = Postulacion::with(['documentos', 'user'])->latest();

        if ($user->hasRole('invitado')) {
            $query->where('user_id', $user->id);
        }

        return Inertia::render('Postulaciones/Index', [
            'postulaciones' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['invitado', 'super_admin'])) {
            abort(403, 'No tienes permiso para crear una postulación.');
        }

        // Si viene de la postulación abierta (pre-aprobada), sus datos ya están
        // registrados y solo falta subir los documentos
        $existente = $user->postulacion;
        $soloDocumentos = $existente?->esperaDocumentos() ?? false;

        if ($existente && ! $soloDocumentos) {
            return redirect()
                ->route('postulaciones.index')
                ->with('error', 'Ya tienes una postulación registrada.');
        }

        $rules = $soloDocumentos ? [] : [
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => ['required', Rule::in(Postulacion::GENEROS)],
            'direccion' => 'required|string|max:500',
            'correo' => 'required|email|max:255',
        ];

        foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
            $rules["documentos.$tipo"] = 'required|file|mimes:pdf|max:2048';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $user, $existente, $soloDocumentos) {
            $postulacion = $soloDocumentos ? $existente : Postulacion::create([
                'user_id' => $user->id,
                'nombre' => $request->nombre,
                'apellido_paterno' => $request->apellido_paterno,
                'apellido_materno' => $request->apellido_materno,
                'genero'=> $request->genero,
                'direccion' => $request->direccion,
                'correo' => $request->correo,
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
            ->with('success', $soloDocumentos
                ? 'Tus documentos se cargaron correctamente y tu postulación será enviada a validación.'
                : 'Tu postulación fue creada correctamente y será enviada a validación.');
    }

    // Crear usuario invitado para que pueda registrar su postulación
    public function storeInvitado(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                //contraseña temporal inicial
                'password' => Hash::make('pass123'),
                'must_change_password' => true,
            ]);

            $user->assignRole('invitado');
        });

        return redirect()
            ->route('postulaciones.index')
            ->with('success', 'Usuario invitado creado correctamente.');
    }

    public function validacion()
    {
        $user = Auth::user();

        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para validar postulaciones.');
        }

        return Inertia::render('Postulaciones/Postulacion', [
            'postulaciones' => Postulacion::with([
                'documentos',
                'user',
                'consejoSolicitado:id,nombre',
            ])
                ->whereIn('estatus', Postulacion::ESTATUS_POR_VALIDAR)
                ->whereHas('documentos')
                ->latest()
                ->get(),
            'consejos' => $this->consejosDisponibles(),
        ]);
    }

    // Detalle de la postulación para revisarla en el primer filtro
    public function show(Postulacion $postulacion)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para ver esta postulación.');
        }

        $postulacion->load(['consejoSolicitado:id,nombre', 'validador:id,name', 'user:id,email'])
            ->loadCount('documentos');

        return Inertia::render('Postulaciones/Show', [
            'postulacion' => $postulacion,
            'enPrimerFiltro' => $postulacion->enPrimerFiltro(),
            'passwordTemporal' => Postulacion::PASSWORD_TEMPORAL,
        ]);
    }

    // Primer filtro: la postulación pasa a la siguiente etapa
    public function preAprobar(Postulacion $postulacion)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para pre-aprobar postulaciones.');
        }

        if (! $postulacion->enPrimerFiltro()) {
            return back()->with('error', 'Esta postulación ya fue revisada.');
        }

        if (User::whereRaw('LOWER(email) = ?', [mb_strtolower($postulacion->correo)])->exists()) {
            return back()->with('error', "Ya existe un usuario con el correo {$postulacion->correo}. "
                . 'No se puede crear su acceso como invitado.');
        }

        DB::transaction(function () use ($postulacion) {
            // Acceso para que la persona entre a subir sus documentos.
            // Contraseña genérica temporal: el sistema le pide cambiarla al iniciar sesión.
            $invitado = User::create([
                'name' => trim("{$postulacion->nombre} {$postulacion->apellido_paterno} {$postulacion->apellido_materno}"),
                'email' => $postulacion->correo,
                'password' => Hash::make(Postulacion::PASSWORD_TEMPORAL),
                'must_change_password' => true,
            ]);
            $invitado->assignRole('invitado');

            $postulacion->update([
                'user_id' => $invitado->id,
                'estatus' => 'pre_aprobada',
                'comentarios_revision' => null,
                'validado_por' => Auth::id(),
                'fecha_validacion' => now(),
            ]);
        });

        return redirect()
            ->route('postulaciones.show', $postulacion)
            ->with('success', 'Postulación pre-aprobada. Se creó su acceso como invitado para que cargue sus documentos.');
    }

    // Primer filtro: la postulación no avanza; los comentarios explican el motivo
    public function noAprobar(Request $request, Postulacion $postulacion)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para revisar postulaciones.');
        }

        $validated = $request->validate([
            'comentarios_revision' => 'required|string|min:10|max:2000',
        ], [
            'comentarios_revision.required' => 'Escribe los comentarios con el motivo por el que no se aprueba.',
            'comentarios_revision.min' => 'Los comentarios deben tener al menos :min caracteres.',
        ]);

        if (! $postulacion->enPrimerFiltro()) {
            return back()->with('error', 'Esta postulación ya fue revisada.');
        }

        $postulacion->update([
            'estatus' => 'no_aprobada',
            'comentarios_revision' => trim($validated['comentarios_revision']),
            'validado_por' => Auth::id(),
            'fecha_validacion' => now(),
        ]);

        return redirect()
            ->route('postulaciones.show', $postulacion)
            ->with('success', 'La postulación se marcó como no aprobada.');
    }

    // Consejos con al menos una fórmula libre, con la ocupación de cada fórmula: { formula: total }
    private function consejosDisponibles()
    {
        $ocupacion = Integrante::whereNotNull('formula')
            ->groupBy('consejo_id', 'formula')
            ->selectRaw('consejo_id, formula, COUNT(*) as total')
            ->get()
            ->groupBy('consejo_id')
            ->map(fn ($filas) => $filas->pluck('total', 'formula'));

        return Consejo::orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (Consejo $consejo) => [
                'id' => $consejo->id,
                'nombre' => $consejo->nombre,
                'ocupacion' => $ocupacion->get($consejo->id, collect()),
            ])
            ->filter(function (array $consejo) {
                for ($formula = 1; $formula <= Integrante::MAX_FORMULAS; $formula++) {
                    if (($consejo['ocupacion'][$formula] ?? 0) < Integrante::INTEGRANTES_POR_FORMULA) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
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
        'consejo_id' => [
            'required',
            'exists:consejos,id',
            // Si la persona eligió un consejo en su postulación, solo puede registrarse en ese
            Rule::when($postulacion->consejo_solicitado_id !== null, [
                Rule::in([$postulacion->consejo_solicitado_id]),
            ]),
        ],
        'formula' => 'required|integer|min:1|max:' . Integrante::MAX_FORMULAS,
    ], [
        'consejo_id.in' => 'La persona solo puede registrarse en el consejo al que se postuló.',
    ]);

    if (! $postulacion->user_id) {
        return redirect()
            ->route('postulaciones.validacion')
            ->with('error', 'Esta postulación no tiene un usuario asociado.');
    }

    return DB::transaction(function () use ($request, $postulacion, $user) {
        if (! in_array($postulacion->estatus, Postulacion::ESTATUS_POR_VALIDAR, true)) {
            abort(422, 'Esta postulación ya fue procesada.');
        }

        // Bloquea el consejo para que dos altas simultáneas no tomen la misma fórmula
        $consejoId = (int) $request->consejo_id;
        Consejo::whereKey($consejoId)->lockForUpdate()->first();
        $formula = (int) $request->formula;
        Integrante::asegurarEspacioEnFormula($consejoId, $formula);

        $archivo = $request->file('acta_resolucion');

        $ruta = $archivo->storeAs(
            'resoluciones',
            'acta_' . $postulacion->id . '_' . time() . '.pdf',
            'public'
        );

        $integrante = Integrante::create([
            'user_id' => $postulacion->user_id,
            'nombre' => $postulacion->nombre,
            'apellido_paterno' => $postulacion->apellido_paterno,
            'apellido_materno' => $postulacion->apellido_materno,
            'correo' => $postulacion->correo,
            'genero' => $postulacion->genero,
            'direccion' => $postulacion->direccion,
            'consejo_id' => $consejoId,
            'formula' => $formula,
            'alta_completada_at' => now(),
            'discapacidad' => null,
            'discapacidad_tipo' => null,
        ]);

        foreach ($postulacion->documentos as $doc) {
            // El expediente del integrante tiene su propia copia en el disco privado,
            // así reemplazar un documento no afecta el archivo de la postulación
            $contenido = Storage::disk('public')->get($doc->archivo);
            if ($contenido === null) {
                throw new \RuntimeException("No se encontró el documento '{$doc->tipo}' de la postulación.");
            }

            $destino = Docu::carpetaDe($integrante->id) . '/' . basename($doc->archivo);
            Docu::disco()->put($destino, $contenido);

            Docu::create([
                'integrante_id' => $integrante->id,
                'tipo' => Docu::TIPOS_DESDE_POSTULACION[$doc->tipo] ?? $doc->tipo,
                'archivo' => $destino,
            ]);
        }

        $fecha = Carbon::parse($request->fecha_validacion);

        Legalidad::create([
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
            'consejo_id' => $consejoId,
            'formula' => $formula,
            'validado_por' => $user->id,
            'fecha_validacion' => $fecha->format('Y-m-d'),
            'acta_resolucion' => $ruta,
        ]);
        //cambio de rol del usuario a integrante
        $postulante = $postulacion->user;
        if ($postulante) {
            // el super_admin conserva su rol y solo se le agrega el de integrante
            if ($postulante->hasRole('super_admin')) {
                $postulante->assignRole('integrante');
            } else {
                $postulante->syncRoles(['integrante']);
            }
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

        if (! in_array($postulacion->estatus, Postulacion::ESTATUS_POR_VALIDAR, true)) {
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