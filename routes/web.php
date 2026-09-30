<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

// Controllers
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\ConsejoController;
use App\Http\Controllers\ConvocatoriaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocuController;
use App\Http\Controllers\JustificanteController;
use App\Http\Controllers\IntegranteController;
use App\Http\Controllers\IntegranteBajaController;
use App\Http\Controllers\LegalidadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PostulacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UserController;

// Rutas públicas
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/about', fn () => Inertia::render('About'))->name('about');

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'privacy'])
    ->name('dashboard');

// Rutas autenticadas
Route::middleware('auth', 'privacy')->group(function () {

    // Aviso de privacidad
    Route::post('/privacy/accept', function (Request $request) {
        $request->user()->update([
            'privacy_accepted' => true,
            'privacy_accepted_at' => now(),
        ]);

        return back();
    })->name('privacy.accept');

    // Consejos
    Route::get('/consejos', [ConsejoController::class, 'index'])
        ->name('consejos.index');

    Route::patch('/consejos/{consejo}/descripcion', [ConsejoController::class, 'updateDescripcion'])
        ->name('consejos.descripcion.update');

    /// Asistencias y participación - consulta
    Route::get('/consejos/asistencias', [ConsejoController::class, 'index'])
    ->name('consejos.asistencias');

    Route::get('/asistencias/{consejo}', [AsistenciaController::class, 'index'])
    ->name('asistencias.index');

    Route::get('/asistencia/{consejo}', [AsistenciaController::class, 'show'])
    ->name('asistencia.show');

    Route::get('/consejos/{consejo}/calendar', [AsistenciaController::class, 'calendar'])
    ->name('asistencia.calendar');

    Route::get('/consejos/{consejo}/asistencias/{integrante}/historial', [AsistenciaController::class, 'history'])
    ->name('asistencia.history');

    Route::get('/consejos/{consejo}/evidencias', [AsistenciaController::class, 'evidencias'])
    ->name('asistencias.evidencias');

   //Asistencias y participación - registro
   Route::middleware('permission:asistencias.crear')->group(function () {

    Route::get('/consejos/{consejo}/asistencias/create', [AsistenciaController::class, 'create'])
        ->name('asistencias.create');

    Route::post('/asistencias/{consejo}', [AsistenciaController::class, 'store'])
        ->name('asistencias.store');

    Route::post('/consejos/{consejo}/asistencias/sesion', [AsistenciaController::class, 'storeSesion'])
        ->name('asistencias.sesion.store');

    // Programar sesión
    Route::post('/asistencias/{consejo}/sesiones/programar', [AsistenciaController::class, 'storeProgramacion'])
        ->name('asistencias.sesiones.programar');
    });

    //Justificantes - integrante
    Route::middleware('role:integrante')->group(function () {

      Route::get('/consejos/{consejo}/justificantes', [JustificanteController::class, 'integrante'])
        ->name('justificantes.integrante');

      Route::post('/consejos/{consejo}/justificantes', [JustificanteController::class, 'store'])
        ->name('justificantes.store');

      // Marcar como vistos los comentarios de justificantes rechazados
      Route::patch('/consejos/{consejo}/justificantes/comentarios-vistos', [JustificanteController::class, 'marcarComentariosVistos'])
        ->name('justificantes.comentarios.vistos');
    });

    //Justificantes - administración
    Route::middleware('role:admin|super_admin')->group(function () {
      Route::get('/consejos/{consejo}/justificantes/admin', [JustificanteController::class, 'admin'])
        ->name('justificantes.admin');
           //visualizar pdf
     Route::get('/justificantes/{asistencia}/ver', [JustificanteController::class, 'show'])
        ->name('justificantes.show');

     //aprobar justificante   
     Route::patch('/justificantes/{asistencia}/aprobar', [JustificanteController::class, 'aprobar'])
        ->name('justificantes.aprobar');

     //rechazar justificante  
      Route::patch('/justificantes/{asistencia}/rechazar', [JustificanteController::class, 'rechazar'])
        ->name('justificantes.rechazar');
     });

     // Convocatorias y sesiones
     Route::middleware('permission:convocatorias.crear')->group(function () {

        Route::get('/consejos/convocatorias', [ConsejoController::class, 'index'])
            ->name('consejos.convocatorias');

        Route::get('/convocatorias/{consejo}', [ConvocatoriaController::class, 'index'])
            ->name('convocatorias.index');

        Route::post('/convocatorias/{consejo}', [ConvocatoriaController::class, 'store'])
            ->name('convocatorias.store');

        Route::patch('/convocatorias/{convocatoria}/estado', [ConvocatoriaController::class, 'toogleEstado'])
            ->name('convocatorias.toogleEstado');

        Route::post('/convocatorias/subir', [ConvocatoriaController::class, 'subir'])
            ->name('convocatorias.subir');
    });

    // Documentos
    Route::get('/documentos/{integrante}', [DocuController::class, 'index'])
        ->name('docu.index');

    Route::middleware('permission:documentos.subir')->group(function () {
        Route::post('/documentos/{integrante}', [DocuController::class, 'store'])
            ->name('docu.store');
    });

    // Validación de documentos
    Route::middleware('role:super_admin|admin')->group(function () {
        Route::patch('/documentos/{documento}/aprobar', [DocuController::class, 'aprobar'])
            ->name('docu.aprobar');

        Route::patch('/documentos/{documento}/rechazar', [DocuController::class, 'rechazar'])
            ->name('docu.rechazar');
    });

    // Ver y descargar documentos
    Route::get('/documento/descargar/{id}', [DocuController::class, 'download'])
        ->name('docu.download');

    Route::get('/documento/ver/{id}', [DocuController::class, 'show'])
        ->name('docu.show');

    // Integrantes
    Route::get('/consejos/{consejo}/integrantes', [IntegranteController::class, 'index'])
        ->middleware('permission:consejos.ver')
        ->name('consejos.integrantes');

    Route::middleware('permission:usuarios.editar')->group(function () {

        Route::resource('integrantes', IntegranteController::class)
            ->except(['index']);

        Route::post('/integrantes/{integrante}/baja', [IntegranteBajaController::class, 'store'])
            ->name('integrantes.baja');
    });

    // Periodo en el cargo
    Route::middleware('permission:legalidad.ver')->group(function () {

        Route::get('/consejos/legalidad', [ConsejoController::class, 'index'])
            ->name('consejos.legalidad');

        Route::get('/legalidad/{consejo}', [LegalidadController::class, 'index'])
            ->name('legalidad.index');

        Route::post('/legalidad/{consejo}', [LegalidadController::class, 'store'])
            ->name('legalidad.store');

        Route::post('/legalidad/{legalidad}/reeleccion', [LegalidadController::class, 'solicitarReeleccion'])
            ->name('legalidad.reeleccion');

        Route::delete('/legalidad/{legalidad}', [LegalidadController::class, 'destroy'])
            ->name('legalidad.destroy');
    });

    // Legalidad - super admin
    Route::middleware('role:super_admin')->group(function () {

        Route::get('/legalidad/estatus/{consejo}', [LegalidadController::class, 'estatus'])
            ->name('legalidad.estatus');

        Route::post('/legalidad/{legalidad}/aprobar', [LegalidadController::class, 'aprobarReeleccion'])
            ->name('legalidad.aprobar');

        Route::post('/legalidad/{legalidad}/rechazar', [LegalidadController::class, 'rechazarReeleccion'])
            ->name('legalidad.rechazar');
    });

    // Reportes
    Route::middleware('permission:reportes.ver')->group(function () {

        Route::get('/consejos/reportes', [ConsejoController::class, 'index'])
            ->name('consejos.reportes');

        Route::get('/consejos/{consejo}/reportes', [ReporteController::class, 'show'])
            ->name('reportes.consejo');
    });

    // Postulaciones
    Route::get('/postulaciones', [PostulacionController::class, 'index'])
        ->name('postulaciones.index');

    Route::post('/postulaciones', [PostulacionController::class, 'store'])
        ->middleware('role:invitado')
        ->name('postulaciones.store');

    // Validación de postulaciones
    Route::middleware('role:admin|super_admin')->group(function () {

        Route::get('/postulaciones/validacion', [PostulacionController::class, 'validacion'])
            ->name('postulaciones.validacion');

        Route::post('/postulaciones/{postulacion}/aprobar', [PostulacionController::class, 'aprobar'])
            ->name('postulaciones.aprobar');

        Route::post('/postulaciones/{postulacion}/rechazar', [PostulacionController::class, 'rechazar'])
            ->name('postulaciones.rechazar');
    });

    // Usuarios
    Route::middleware([
        'permission:usuarios.crear',
        'role:super_admin|admin',
    ])->group(function () {

        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');
    });

    // Perfil
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

// Verificación de sesión
Route::get('/session/check', function () {
    return response()->json([
        'authenticated' => Auth::check(),
    ]);
})->name('session.check');

// Auth
require __DIR__ . '/auth.php';