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

class IntegranteController extends Controller
{
    public function index(Consejo $consejo)
    {   
        Gate::authorize('viewAny', [Integrante::class, $consejo]);

        /** @var User $user */
        $user = auth()->user();
        $integranteActualId = null;

        // Si el usuario es un integrante, obtenemos su ID y filtramos los integrantes por la misma fórmula
        if ($user->hasRole('integrante')) {
            $integranteActual = $user->integrante;
            // Si el integrante no tiene un registro asociado, abortamos con un error 403
            abort_unless($integranteActual, 403);
            $integranteActualId = $integranteActual->id;
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
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Integrante::class);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'discapacidad' => 'nullable|string|max:25',
            'discapacidad_tipo' => 'nullable|string|max:255|required_if:discapacidad,si',
            'puesto' => 'required|string|max:255',
            'correo' => 'required|email|unique:integrantes,correo|unique:users,email',
            'consejo_id' => 'required|exists:consejos,id',
            'formula' => 'required|integer|min:1',
        ]);
        DB::transaction(function () use ($validated){
            //crear usuario          
            $user = User::create(['name' => $validated['nombre'] . ' ' . $validated['apellido'],
             'email' => $validated['correo'], 
             //contrseña temporal inicial
             'password' => Hash::make('pass123'),
             'must_change_password' => true,
            ]);
            //ASIGNAR ROL
            $user->assignRole('integrante');
            //crear integrante y asociar el user_id
            $validated['user_id'] = $user->id;
            
            Integrante::create($validated);
        });

        return redirect()->route('consejos.integrantes', $request->consejo_id);
    }

    public function update(Request $request, Integrante $integrante)
    { 
        Gate::authorize('update', $integrante);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'genero' => 'nullable|string|max:50',
            'direccion' => 'nullable|string|max:500',
            'discapacidad' => 'nullable|string|max:25',
            'discapacidad_tipo' => 'nullable|string|max:255|required_if:discapacidad,si',
            'puesto' => 'required|string|max:255',
            'correo' => ['required','email',
                'unique:integrantes,correo,' . $integrante->id,
                'unique:users,email,' . $integrante->user_id,
        ],

    ]);

    DB::transaction(function () use ($validated, $integrante) {
        //ACTUALIZAR INTEGRANTE
        $integrante->update($validated);
        //ACTUALIZAR USUARIO ASOCIADO
        $integrante->user->update([
            'name' => $validated['nombre'] . ' ' . $validated['apellido'],
            'email' => $validated['correo'],
        ]);
    });

    return redirect()->route('consejos.integrantes',$integrante->consejo_id); 
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
}
