<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\Postulacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return Inertia::render('Postulaciones/Publica/Create', [
            'consejos' => Consejo::orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion'])
                ->map(fn (Consejo $consejo) => [
                    'id' => $consejo->id,
                    'nombre' => $consejo->nombre,
                    'descripcion' => $consejo->descripcion,
                    'lleno' => Integrante::consejoLleno($consejo->id),
                ]),
            'perfiles' => Postulacion::PERFILES,
            'generos' => Postulacion::GENEROS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Campo trampa: las personas no lo ven; si viene lleno, es un bot.
        // Se responde como si todo hubiera salido bien para no darle pistas.
        if ($request->filled('sitio_web')) {
            return redirect()->route('postulate.enviada');
        }

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => ['required', Rule::in(Postulacion::GENEROS)],
            'correo' => 'required|email|max:255',
            'direccion' => 'required|string|max:500',
            'consejo_solicitado_id' => 'required|exists:consejos,id',
            'perfil' => ['required', Rule::in(Postulacion::PERFILES)],
            'perfil_descripcion' => 'required|string|min:30|max:1500',
            'aviso_privacidad' => 'accepted',
        ], [
            'perfil_descripcion.min' => 'Cuéntanos un poco más sobre ti (al menos :min caracteres).',
            'aviso_privacidad.accepted' => 'Para enviar tu postulación es necesario aceptar el aviso de privacidad.',
        ]);

        $correo = mb_strtolower(trim($validated['correo']));

        // Una sola postulación en revisión por correo
        $enRevision = Postulacion::whereRaw('LOWER(correo) = ?', [$correo])
            ->where('estatus', 'pendiente')
            ->exists();

        if ($enRevision) {
            return back()->withErrors([
                'correo' => 'Ya hay una postulación en revisión con este correo. '
                    . 'Te contactaremos por este medio cuando haya una resolución.',
            ])->withInput();
        }

        $postulacion = DB::transaction(function () use ($validated, $correo) {
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

            $postulacion->update(['folio' => Postulacion::generarFolio($postulacion->id)]);

            return $postulacion;
        });

        // El folio viaja en la sesión: la página de confirmación no se puede abrir con folios ajenos
        return redirect()
            ->route('postulate.enviada')
            ->with('postulacion_enviada', [
                'folio' => $postulacion->folio,
                'nombre' => $postulacion->nombre,
                'correo' => $postulacion->correo,
                'consejo' => $postulacion->consejoSolicitado?->nombre,
            ]);
    }

    public function enviada(Request $request): Response|RedirectResponse
    {
        $datos = $request->session()->get('postulacion_enviada');

        if (! $datos) {
            return redirect()->route('postulate.create');
        }

        return Inertia::render('Postulaciones/Publica/Enviada', $datos);
    }
}
