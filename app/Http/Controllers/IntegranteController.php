<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Integrante;
use App\Models\Consejo;
use App\Models\User;
use App\Models\IntegranteBaja;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Notifications\BienvenidaIntegrante;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IntegranteController extends Controller
{
    public function index(Consejo $consejo)
    {   
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        /** @var User $user */
        $user = auth()->user();
        $integranteActualId = null;
        $altaPendiente = false;

        // Si el usuario es un integrante, obtenemos su ID y filtramos los integrantes por la misma fórmula
        if ($user->hasRole('integrante')) {
            $integranteActual = $user->integrante;
            // Si el integrante no tiene un registro asociado, abortamos con un error 403
            abort_unless($integranteActual, 403);
            $integranteActualId = $integranteActual->id;
            $altaPendiente = $integranteActual->alta_completada_at === null;
            $integrantes = $consejo->integrantes()
                ->where('formula', $integranteActual->formula)
                ->get();
            $integrantes
                ->where('id', $integranteActual->id)
                ->load('documentos');
        //admin y super_admin pueden ver todos los integrantes del consejo
        } else {
            $integrantes = $consejo->integrantes()
                ->with('documentos')
                ->get();
        }
        return Inertia::render('Integrantes/Index', [
            'consejo' => $consejo,
            'integrantes' => $integrantes,
            'integranteActualId' => $integranteActualId,
            'consejoLleno' => Integrante::consejoLleno($consejo->id),
            'altaPendiente' => $altaPendiente,
        ]);
    }

    // El admin da de alta en la plataforma a quien ya forma parte de un consejo:
    // crea su usuario (contraseña temporal) y un registro de integrante con datos mínimos.
    // El integrante captura el resto de sus datos con "Darme de alta".
    public function store(Request $request)
    {
        Gate::authorize('create', Integrante::class);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email|unique:integrantes,correo',
            // Confirmación del correo: debe escribirse igual (sin distinguir mayúsculas ni espacios)
            'email_confirmacion' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    if ($this->normalizarCorreo($value) !== $this->normalizarCorreo($request->input('email'))) {
                        $fail('Los correos no coinciden. Revise que ambos estén escritos igual.');
                    }
                },
            ],
            'consejo_id' => 'required|exists:consejos,id',
            'formula' => ['required', 'integer', 'min:1', 'max:' . Integrante::MAX_FORMULAS],
            'cargo' => ['required', Rule::in(Integrante::CARGOS)],
        ], [
            'email_confirmacion.required' => 'Vuelva a escribir el correo para confirmarlo.',
        ]);
        unset($validated['email_confirmacion']);

        // Contraseña temporal única por integrante (se envía por correo; sin símbolos para copiarla fácil)
        $passwordTemporal = Str::password(12, symbols: false);

        $integrante = DB::transaction(function () use ($validated, $passwordTemporal) {
            // Bloquea el consejo para que dos altas simultáneas no llenen de más la fórmula
            Consejo::whereKey($validated['consejo_id'])->lockForUpdate()->first();
            Integrante::asegurarEspacioEnFormula($validated['consejo_id'], $validated['formula']);
            Integrante::asegurarCargoDisponible($validated['consejo_id'], $validated['formula'], $validated['cargo']);

            $user = User::create([
                'name' => Integrante::formatearNombreCompleto(
                    $validated['nombre'], $validated['apellido_paterno'], $validated['apellido_materno'] ?? null
                ),
                'email' => $validated['email'],
                'password' => Hash::make($passwordTemporal),
                'must_change_password' => true,
            ]);
            $user->assignRole('integrante');

            return Integrante::create([
                'user_id' => $user->id,
                'nombre' => $validated['nombre'],
                'apellido_paterno' => $validated['apellido_paterno'],
                'apellido_materno' => $validated['apellido_materno'] ?? null,
                'correo' => $validated['email'],
                'consejo_id' => $validated['consejo_id'],
                'formula' => $validated['formula'],
                'cargo' => $validated['cargo'],
                'alta_completada_at' => null,
            ]);
        });

        // Se envía después del commit: si la transacción falla, nadie recibe una contraseña
        // de una cuenta inexistente; si el correo falla, el integrante ya quedó guardado.
        $redirect = redirect()->route('consejos.integrantes', $validated['consejo_id']);

        try {
            $integrante->user->notify(new BienvenidaIntegrante($integrante, $passwordTemporal));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de bienvenida al integrante', [
                'integrante_id' => $integrante->id,
                'error' => $e->getMessage(),
            ]);

            return $redirect->with('warning', 'Usuario integrante creado, pero no se pudo enviar el correo de bienvenida.');
        }

        return $redirect->with('success', 'Usuario integrante creado. Se le envió su acceso por correo.');
    }

    // "Darme de alta": el integrante captura sus propios datos una sola vez.
    // El cargo, el correo, el consejo y la fórmula los definió el admin y no se modifican aquí.
    public function completarAlta(Request $request, Integrante $integrante)
    {
        Gate::authorize('update', $integrante);

        abort_if($integrante->alta_completada_at !== null, 403, 'Ya completaste tu alta.');

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => 'required|string|max:50',
            'direccion' => 'required|string|max:500',
            'discapacidad' => 'required|in:si,no',
            'discapacidad_tipo' => 'nullable|string|max:255|required_if:discapacidad,si',
        ]);

        if ($validated['discapacidad'] === 'no') {
            $validated['discapacidad_tipo'] = null;
        }

        DB::transaction(function () use ($validated, $integrante) {
            $integrante->update($validated + ['alta_completada_at' => now()]);
            $integrante->user->update([
                'name' => Integrante::formatearNombreCompleto(
                $validated['nombre'], $validated['apellido_paterno'], $validated['apellido_materno'] ?? null
            ),
            ]);
        });

        return redirect()->route('consejos.integrantes', $integrante->consejo_id);
    }

    public function update(Request $request, Integrante $integrante)
    { 
        Gate::authorize('update', $integrante);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'genero' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'discapacidad' => 'nullable|string|max:25',
            'discapacidad_tipo' => 'nullable|string|max:255|required_if:discapacidad,si',
            'cargo' => ['required', Rule::in(Integrante::CARGOS)],
            'correo' => ['required','email',
                'unique:integrantes,correo,' . $integrante->id,
                'unique:users,email,' . $integrante->user_id,
        ],
            // Confirmación obligatoria solo si el correo cambia
            'correo_confirmacion' => [
                Rule::requiredIf(fn () => $this->normalizarCorreo($request->input('correo'))
                    !== $this->normalizarCorreo($integrante->correo)),
                'nullable', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($request) {
                    if ($this->normalizarCorreo($value) !== $this->normalizarCorreo($request->input('correo'))) {
                        $fail('Los correos no coinciden. Revise que ambos estén escritos igual.');
                    }
                },
            ],
    ], [
        'correo_confirmacion.required' => 'Vuelva a escribir el correo para confirmarlo.',
    ]);
    unset($validated['correo_confirmacion']);

    DB::transaction(function () use ($validated, $integrante) {
        // Solo se valida si cambia el cargo, para que corregir otros datos nunca se bloquee
        if ($validated['cargo'] !== $integrante->cargo) {
            Consejo::whereKey($integrante->consejo_id)->lockForUpdate()->first();
            Integrante::asegurarCargoDisponible($integrante->consejo_id, $integrante->formula, $validated['cargo']);
        }

        //ACTUALIZAR INTEGRANTE
        $integrante->update($validated);
        //ACTUALIZAR USUARIO ASOCIADO
        $integrante->user->update([
            'name' => Integrante::formatearNombreCompleto(
                $validated['nombre'], $validated['apellido_paterno'], $validated['apellido_materno'] ?? null
            ),
            'email' => $validated['correo'],
        ]);
    });

    return redirect()->route('consejos.integrantes',$integrante->consejo_id); 
    }

    // Mueve al integrante a otra fórmula del mismo consejo, solo si esa fórmula tiene lugar libre
    public function moverFormula(Request $request, Integrante $integrante)
    {
        Gate::authorize('update', $integrante);

        $validated = $request->validate([
            'formula' => ['required', 'integer', 'min:1', 'max:' . Integrante::MAX_FORMULAS],
        ]);
        $formula = (int) $validated['formula'];

        if ($formula === (int) $integrante->formula) {
            throw ValidationException::withMessages([
                'formula' => 'El integrante ya pertenece a esta fórmula.',
            ]);
        }

        DB::transaction(function () use ($integrante, $formula) {
            // Bloquea el consejo para que dos movimientos simultáneos no llenen de más la fórmula
            Consejo::whereKey($integrante->consejo_id)->lockForUpdate()->first();

            Integrante::asegurarEspacioEnFormula($integrante->consejo_id, $formula, $integrante->id);
            Integrante::asegurarCargoDisponible($integrante->consejo_id, $formula, $integrante->cargo);

            $integrante->update(['formula' => $formula]);
        });

        return redirect()->route('consejos.integrantes', $integrante->consejo_id);
    }

    public function destroy(Request $request, Integrante $integrante)
    {
        Gate::authorize('delete', $integrante);
        // Validación del formulario de baja
        $request->validate([
            'motivo' => 'required|in:inasistencia,sancion,fin_periodo,renuncia',
            'fecha_baja' => 'required|date',
            'evidencia_pdf' => 'required|file|mimes:pdf|max:5120',
        ]);

        DB::transaction(function () use ($request, $integrante) {
            // Guardar PDF en storage/app/public/bajas
            $pdfPath = $request->file('evidencia_pdf')->store('bajas', 'public');

            // Registrar baja histórica
            IntegranteBaja::create([
                'integrante_id' => $integrante->id,
                'user_id'       => $integrante->user_id,
                'consejo_id'    => $integrante->consejo_id,
                'nombre'        => $integrante->nombre,
                'apellido'      => $integrante->apellido,
                'motivo'        => $request->motivo,
                'fecha_baja'    => $request->fecha_baja,
                'evidencia_pdf' => $pdfPath,
            ]);

            // Eliminar integrante de la tabla principal
            $integrante->delete();
        });

        return redirect()->route('consejos.integrantes', $integrante->consejo_id);
    }

    // Comparación de correos sin distinguir mayúsculas ni espacios
    private function normalizarCorreo(?string $correo): string
    {
        return mb_strtolower(trim((string) $correo));
    }
}
