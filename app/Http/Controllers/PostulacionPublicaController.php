<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Postulacion;
use App\Notifications\PostulacionRecibida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Primer filtro de postulaciones: abierto a la ciudadanía, sin inicio de sesión
 * y sin documentos. Si la postulación se aprueba en este filtro, se pasa al
 * segundo, donde se crea la cuenta "invitado" y se piden los documentos.
 */
class PostulacionPublicaController extends Controller
{
    public function create(): Response
    {
        // Los consejos sin lugares no se muestran a la ciudadanía
        return Inertia::render('Postulaciones/Publica/Create', [
            'consejos' => Consejo::orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion'])
                ->reject(fn (Consejo $consejo) => Integrante::consejoLleno($consejo->id))
                ->values(),
            'perfiles' => Postulacion::PERFILES,
            'generos' => Postulacion::GENEROS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo trampa: las personas no lo ven; si viene lleno, es un bot.
        // Se responde como si todo hubiera salido bien para no darle pistas.
        if ($request->filled('sitio_web')) {
            return redirect()->route('postulacion-abierta.enviada');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => ['required', Rule::in(Postulacion::GENEROS)],
            'correo' => 'required|email|max:255',
            'direccion' => 'required|string|max:500',
            'consejo_solicitado_id' => [
                'required',
                'exists:consejos,id',
                function ($attribute, $value, $fail) {
                    if (Integrante::consejoLleno((int) $value)) {
                        $fail('Este consejo ya no tiene lugares disponibles. Por favor, elija otro.');
                    }
                },
            ],
            'perfil' => ['required', Rule::in(Postulacion::PERFILES)],
            'perfil_descripcion' => 'required|string|min:30|max:1500',
            'aviso_privacidad' => 'accepted',
        ], [
            'perfil_descripcion.min' => 'Por favor, cuéntenos un poco más sobre usted (al menos :min caracteres).',
            'aviso_privacidad.accepted' => 'Para enviar su postulación es necesario aceptar el aviso de privacidad.',
        ]);

        $correo = mb_strtolower(trim($validated['correo']));

        // Una sola postulación en revisión por correo
        $enRevision = Postulacion::whereRaw('LOWER(correo) = ?', [$correo])
            ->whereIn('estatus', ['pendiente', 'pre_aprobada'])
            ->exists();

        if ($enRevision) {
            return back()->withErrors([
                'correo' => 'Ya hay una postulación en revisión con este correo. '
                    . 'Le contactaremos por este medio cuando haya una resolución.',
            ])->withInput();
        }

        $postulacion = Postulacion::create([
            'nombre' => $validated['nombre'],
            'apellido_paterno' => $validated['apellido_paterno'],
            'apellido_materno' => $validated['apellido_materno'] ?? null,
            'genero' => $validated['genero'],
            'correo' => $correo,
            'direccion' => $validated['direccion'],
            'consejo_solicitado_id' => $validated['consejo_solicitado_id'],
            'perfil' => $validated['perfil'],
            'perfil_descripcion' => $validated['perfil_descripcion'],
            'estatus' => 'pendiente',
            'fecha_postulacion' => now(),
            'aviso_privacidad_at' => now(),
        ]);

        // Si el correo falla, la postulación ya quedó guardada: solo se registra el error
        try {
            Notification::route('mail', $correo)->notify(new PostulacionRecibida($postulacion));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de confirmación de postulación', [
                'postulacion_id' => $postulacion->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Los datos viajan en la sesión: la página de confirmación solo se ve tras enviar
        return redirect()
            ->route('postulacion-abierta.enviada')
            ->with('postulacion_enviada', [
                'nombre' => $postulacion->nombre,
                'correo' => $postulacion->correo,
                'consejo' => $postulacion->consejoSolicitado?->nombre,
            ]);
    }

    public function enviada(Request $request): Response|RedirectResponse
    {
        $datos = $request->session()->get('postulacion_enviada');

        if (! $datos) {
            return redirect()->route('postulacion-abierta.create');
        }

        return Inertia::render('Postulaciones/Publica/Enviada', $datos);
    }
}
