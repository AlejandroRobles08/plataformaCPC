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
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => 'required|in:Mujer,Hombre,No binario,Otro,Prefiero no responder',
            'direccion' => 'required|string|max:500',
            'correo' => 'required|email|max:255',
        ];

        foreach (Postulacion::TIPOS_DOCUMENTOS as $tipo) {
            $rules["documentos.$tipo"] = 'required|file|mimes:pdf|max:2048';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $user) {
            $postulacion = Postulacion::create([
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
            ->with('success', 'Tu postulación fue creada correctamente y será enviada a validación.');
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
            ])
                ->where('estatus', 'pendiente')
                ->latest()
                ->get(),
            'consejos' => Consejo::orderBy('nombre')->get(['id', 'nombre']),
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
        'consejo_id' => 'required|exists:consejos,id',
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

        // Bloquea el consejo para que dos altas simultáneas no tomen la misma fórmula
        $consejoId = (int) $request->consejo_id;
        Consejo::whereKey($consejoId)->lockForUpdate()->first();
        $formula = Integrante::siguienteFormula($consejoId);

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