<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Docu;
use App\Models\Integrante;
use App\Models\Legalidad;
use App\Models\Postulacion;
use App\Models\PostulacionDocumento;
use App\Models\User;
use App\Notifications\PostulacionNoAprobada;
use App\Notifications\PostulacionPreAprobada;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            // Alerta roja del botón "Panel de validación": postulaciones con documentos por revisar
            'documentosPorRevisar' => $user->hasAnyRole(['admin', 'super_admin'])
                ? Postulacion::conDocumentosPorRevisar()->count()
                : 0,
        ]);
    }

    // Expediente de la persona postulante: ve el estado de cada documento, las observaciones
    // de los que no se aprobaron y puede subir una nueva versión de ellos
    public function misDocumentos()
    {
        $postulacion = Auth::user()->postulacion;

        if (! $postulacion || $postulacion->documentos()->doesntExist()) {
            return redirect()
                ->route('postulaciones.index')
                ->with('error', 'Aún no has cargado los documentos de tu postulación.');
        }

        return Inertia::render('Postulaciones/Documentos', [
            'postulacion' => $postulacion->load('documentos'),
            'nombresDocumentos' => Postulacion::NOMBRES_DOCUMENTOS,
            'editable' => in_array($postulacion->estatus, Postulacion::ESTATUS_POR_VALIDAR, true),
        ]);
    }

    // Reemplaza los documentos que no fueron aprobados; vuelven a quedar en revisión
    public function reemplazarDocumentos(Request $request)
    {
        $postulacion = Auth::user()->postulacion;

        if (! $postulacion || ! in_array($postulacion->estatus, Postulacion::ESTATUS_POR_VALIDAR, true)) {
            abort(403, 'Tu postulación ya no admite cambios en sus documentos.');
        }

        $reglas = [];
        foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
            $reglas[$tipo] = 'nullable|file|mimes:pdf|max:2048';
        }
        $request->validate($reglas, [
            'mimes' => 'El documento debe ser un archivo PDF.',
            'max' => 'El documento no debe superar los 2 MB.',
        ]);

        $tiposRecibidos = array_values(array_filter(
            Postulacion::TIPOS_DOCUMENTOS,
            fn ($tipo) => $request->hasFile($tipo)
        ));

        if (empty($tiposRecibidos)) {
            throw ValidationException::withMessages([
                'documentos' => 'Selecciona al menos un documento para guardar.',
            ]);
        }

        $existentes = $postulacion->documentos()->whereIn('tipo', $tiposRecibidos)->get()->keyBy('tipo');

        // Un documento aprobado o en revisión no se puede reemplazar
        $bloqueados = $existentes->reject(fn (PostulacionDocumento $doc) => $doc->puedeReemplazarse());
        if ($bloqueados->isNotEmpty()) {
            throw ValidationException::withMessages(
                $bloqueados->mapWithKeys(fn (PostulacionDocumento $doc) => [
                    $doc->tipo => $doc->estatus === PostulacionDocumento::APROBADO
                        ? 'Este documento ya fue aprobado y no puede reemplazarse.'
                        : 'Este documento está en revisión; podrás reemplazarlo si no es aprobado.',
                ])->all()
            );
        }

        $nuevos = [];
        $anteriores = [];

        try {
            DB::transaction(function () use ($request, $postulacion, $tiposRecibidos, $existentes, &$nuevos, &$anteriores) {
                foreach ($tiposRecibidos as $tipo) {
                    $ruta = $request->file($tipo)->store('postulaciones', 'public');
                    if (! $ruta) {
                        throw new \RuntimeException("No fue posible guardar el documento {$tipo}.");
                    }
                    $nuevos[] = $ruta;

                    $datos = [
                        'archivo' => $ruta,
                        'estatus' => PostulacionDocumento::PENDIENTE,
                        'observacion' => null,
                        'validado_por' => null,
                        'validado_at' => null,
                    ];

                    if ($documento = $existentes->get($tipo)) {
                        $anteriores[] = $documento->archivo;
                        $documento->update($datos);
                    } else {
                        $postulacion->documentos()->create($datos + ['tipo' => $tipo]);
                    }
                }
            });
        } catch (\Throwable $e) {
            // Si algo falla no deben quedar archivos huérfanos
            Storage::disk('public')->delete($nuevos);
            throw $e;
        }

        // Los archivos reemplazados se eliminan solo cuando la base de datos ya se actualizó
        Storage::disk('public')->delete($anteriores);

        return redirect()
            ->route('postulaciones.documentos')
            ->with('success', 'Documentos guardados correctamente. Quedaron en revisión.');
    }

    // Segundo filtro: aprobar un documento del expediente de la postulación
    public function aprobarDocumento(PostulacionDocumento $documento)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para validar documentos.');
        }

        if ($error = $this->motivoDocumentoNoValidable($documento)) {
            return back()->with('error', $error);
        }

        $documento->update([
            'estatus' => PostulacionDocumento::APROBADO,
            'observacion' => null,
            'validado_por' => Auth::id(),
            'validado_at' => now(),
        ]);

        return back()->with('success', 'Documento aprobado correctamente.');
    }

    // Segundo filtro: no aprobar un documento; la observación la ve la persona en su expediente
    public function rechazarDocumento(Request $request, PostulacionDocumento $documento)
    {
        if (! Auth::user()->hasAnyRole(['admin', 'super_admin'])) {
            abort(403, 'No tienes permiso para validar documentos.');
        }

        $validated = $request->validate([
            'observacion' => 'required|string|max:2000',
        ], [
            'observacion.required' => 'Escribe la observación con el motivo por el que no se aprueba.',
        ]);

        if ($error = $this->motivoDocumentoNoValidable($documento)) {
            return back()->with('error', $error);
        }

        $documento->update([
            'estatus' => PostulacionDocumento::RECHAZADO,
            'observacion' => trim($validated['observacion']),
            'validado_por' => Auth::id(),
            'validado_at' => now(),
        ]);

        return back()->with('success', 'Documento no aprobado. La persona verá la observación en su expediente.');
    }

    private function motivoDocumentoNoValidable(PostulacionDocumento $documento): ?string
    {
        if (! in_array($documento->postulacion?->estatus, Postulacion::ESTATUS_POR_VALIDAR, true)) {
            return 'Esta postulación ya fue procesada.';
        }

        return $documento->puedeValidarse() ? null : $documento->motivoNoValidable();
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
            'nombresDocumentos' => Postulacion::NOMBRES_DOCUMENTOS,
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

        // Contraseña temporal única (se envía por correo; sin símbolos para copiarla fácil).
        // El sistema le pide cambiarla y aceptar el aviso de privacidad al iniciar sesión.
        $passwordTemporal = Str::password(12, symbols: false);

        DB::transaction(function () use ($postulacion, $passwordTemporal) {
            // Acceso para que la persona entre a subir sus documentos
            $invitado = User::create([
                'name' => trim("{$postulacion->nombre} {$postulacion->apellido_paterno} {$postulacion->apellido_materno}"),
                'email' => $postulacion->correo,
                'password' => Hash::make($passwordTemporal),
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

        // Se envía después del commit: si la transacción falla, nadie recibe una contraseña
        // de una cuenta inexistente; si el correo falla, el acceso ya quedó creado.
        $redirect = redirect()->route('postulaciones.show', $postulacion);

        if (! $this->enviarCorreo($postulacion, new PostulacionPreAprobada($postulacion, $passwordTemporal))) {
            // Única vez que se muestra la contraseña: así el admin puede compartirla por otro medio
            return $redirect->with('warning', 'Postulación pre-aprobada, pero no se pudo enviar el correo. '
                . "Comparta el acceso por otro medio. Usuario: {$postulacion->correo} · Contraseña temporal: {$passwordTemporal}");
        }

        return $redirect->with('success', 'Postulación pre-aprobada. Se le envió por correo su acceso y la lista de documentos.');
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

        $enviado = $this->enviarCorreo($postulacion, new PostulacionNoAprobada($postulacion));

        return redirect()
            ->route('postulaciones.show', $postulacion)
            ->with($enviado ? 'success' : 'warning', $enviado
                ? 'La postulación se marcó como no aprobada. Se le notificó por correo.'
                : 'La postulación se marcó como no aprobada, pero no se pudo enviar el correo de notificación.');
    }

    // Envía un correo al postulante sin interrumpir el flujo si el servidor de correo falla.
    // La postulación ya quedó guardada: solo se registra el error y se avisa al admin.
    private function enviarCorreo(Postulacion $postulacion, \Illuminate\Notifications\Notification $notificacion): bool
    {
        try {
            Notification::route('mail', $postulacion->correo)->notify($notificacion);

            return true;
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de la postulación', [
                'postulacion_id' => $postulacion->id,
                'notificacion' => class_basename($notificacion),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // Consejos con al menos una fórmula libre, con la ocupación de cada fórmula: { formula: total }
    // y los cargos ya asignados en cada una: { formula: [cargos] }
    private function consejosDisponibles()
    {
        $ocupacion = Integrante::whereNotNull('formula')
            ->groupBy('consejo_id', 'formula')
            ->selectRaw('consejo_id, formula, COUNT(*) as total')
            ->get()
            ->groupBy('consejo_id')
            ->map(fn ($filas) => $filas->pluck('total', 'formula'));

        $cargos = Integrante::whereNotNull('formula')
            ->whereNotNull('cargo')
            ->get(['consejo_id', 'formula', 'cargo'])
            ->groupBy('consejo_id')
            ->map(fn ($filas) => $filas->groupBy('formula')->map(fn ($f) => $f->pluck('cargo')->values()));

        return Consejo::orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (Consejo $consejo) => [
                'id' => $consejo->id,
                'nombre' => $consejo->nombre,
                'ocupacion' => $ocupacion->get($consejo->id, collect()),
                'cargos' => $cargos->get($consejo->id, collect()),
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
        'cargo' => ['required', Rule::in(Integrante::CARGOS)],
    ], [
        'consejo_id.in' => 'La persona solo puede registrarse en el consejo al que se postuló.',
    ]);

    if (! $postulacion->user_id) {
        return redirect()
            ->route('postulaciones.validacion')
            ->with('error', 'Esta postulación no tiene un usuario asociado.');
    }

    if (! $postulacion->expedienteAprobado()) {
        return redirect()
            ->route('postulaciones.validacion')
            ->with('error', 'Antes de aprobar la postulación, todos los documentos del expediente deben estar aprobados.');
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
        // Cada fórmula tiene una persona titular y una suplente
        Integrante::asegurarCargoDisponible($consejoId, $formula, $request->cargo);

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
            'cargo' => $request->cargo,
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

            // Ya se validaron en la postulación: llegan aprobados al archivo digital
            Docu::create([
                'integrante_id' => $integrante->id,
                'tipo' => Docu::TIPOS_DESDE_POSTULACION[$doc->tipo] ?? $doc->tipo,
                'archivo' => $destino,
                'estatus' => Docu::APROBADO,
                'validado_por' => $doc->validado_por,
                'validado_at' => $doc->validado_at,
            ]);
        }

        // Los documentos de la postulación ya se validaron en ese módulo: no deben encender
        // el aviso de "documentos revisados" del botón Documentos (Archivo digital).
        // Solo las revisiones posteriores, hechas en el Archivo digital, lo encienden.
        $integrante->forceFill(['revisiones_vistas_at' => now()])->saveQuietly();

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

        $enviado = $this->enviarCorreo($postulacion, new PostulacionNoAprobada($postulacion));

        return redirect()
            ->route('postulaciones.validacion')
            ->with($enviado ? 'success' : 'warning', $enviado
                ? 'Postulación rechazada correctamente. Se le notificó por correo.'
                : 'Postulación rechazada, pero no se pudo enviar el correo de notificación.');
    }
}