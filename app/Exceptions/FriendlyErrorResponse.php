<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Convierte los errores HTTP (403, 404, 419, 500...) en mensajes comprensibles
 * para el usuario final, en lugar de los textos técnicos en inglés de Laravel
 * ("This action is unauthorized.", "User does not have the right roles.", etc.).
 *
 * - Acciones (POST/PATCH/DELETE) hechas con Inertia: regresa a la página anterior
 *   y muestra el mensaje en una alerta (flash "error_global").
 * - Navegación (GET): muestra la página Error.vue con un mensaje según el rol.
 * - Peticiones JSON (axios/fetch): responde JSON con el mensaje en español.
 */
class FriendlyErrorResponse
{
    private const MENSAJES = [
        403 => [
            'titulo' => 'Acceso restringido',
            'mensaje' => 'No tienes permisos para acceder a este módulo o realizar esta acción.',
        ],
        404 => [
            'titulo' => 'Página no encontrada',
            'mensaje' => 'La página o el registro que buscas no existe o ya fue eliminado.',
        ],
        405 => [
            'titulo' => 'Acción no disponible',
            'mensaje' => 'La acción que intentaste realizar no está disponible desde esta página.',
        ],
        413 => [
            'titulo' => 'Archivo demasiado grande',
            'mensaje' => 'Los archivos que intentas subir superan el tamaño permitido. Reduce su tamaño o súbelos de uno en uno.',
        ],
        419 => [
            'titulo' => 'Tu sesión expiró',
            'mensaje' => 'Por seguridad, la página caducó tras un tiempo de inactividad. Vuelve a intentarlo.',
        ],
        429 => [
            'titulo' => 'Demasiados intentos',
            'mensaje' => 'Realizaste demasiadas solicitudes en poco tiempo. Espera un momento antes de volver a intentarlo.',
        ],
        500 => [
            'titulo' => 'Algo salió mal',
            'mensaje' => 'Ocurrió un error inesperado. Ya quedó registrado; inténtalo de nuevo en unos minutos.',
        ],
        503 => [
            'titulo' => 'Plataforma en mantenimiento',
            'mensaje' => 'Estamos realizando mejoras en la plataforma. Vuelve a intentarlo en unos minutos.',
        ],
    ];

    private const MENSAJE_GENERICO = [
        'titulo' => 'No pudimos completar la solicitud',
        'mensaje' => 'Ocurrió un problema al procesar tu solicitud. Inténtalo de nuevo.',
    ];

    // Textos por defecto de Laravel/Symfony/Spatie: técnicos y en inglés
    private const MENSAJES_TECNICOS = [
        '/^This action is unauthorized/i',
        '/^User does not have/i',
        '/^Authorizable class/i',
        '/^Necessary (roles|permissions)/i',
        '/^No query results/i',
        '/^The route .* could not be found/i',
        '/^The .* method is not supported/i',
        '/^(Not found|Forbidden|Bad request|Unauthorized|Service Unavailable)\.?$/i',
        '/^Too Many Attempts/i',
        '/^CSRF token mismatch/i',
        '/^Unauthenticated/i',
        '/^Whoops/i',
        '/^Server Error/i',
    ];

    public function __invoke(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        // Los errores de validación los maneja Inertia en cada formulario
        if ($status < 400 || $e instanceof ValidationException) {
            return $response;
        }

        // En desarrollo se conserva la pantalla de depuración para errores del servidor
        if ($status >= 500 && config('app.debug')) {
            return $response;
        }

        $contenido = $this->contenido($status, $e, $request);
        $esInertia = $request->header('X-Inertia') !== null;

        if (! $esInertia && $request->expectsJson()) {
            return response()->json(['message' => $contenido['mensaje']], $status);
        }

        // Una acción fallida no debe sacar al usuario de la página en la que estaba
        if ($esInertia && ! $request->isMethod('GET') && $request->hasSession()) {
            return redirect()->back(303)->with('error_global', [
                'titulo' => $contenido['titulo'],
                'mensaje' => $contenido['mensaje'],
            ]);
        }

        return Inertia::render('Error', array_merge($contenido, [
            'status' => $status,
            'autenticado' => $request->user() !== null,
        ]))
            ->toResponse($request)
            ->setStatusCode($status);
    }

    private function contenido(int $status, Throwable $e, Request $request): array
    {
        $base = self::MENSAJES[$status] ?? self::MENSAJE_GENERICO;

        $mensaje = $this->mensajePersonalizado($status, $e) ?? $base['mensaje'];

        if ($status === 429 && $segundos = $this->segundosDeEspera($e)) {
            $mensaje = "Realizaste demasiadas solicitudes en poco tiempo. Espera {$segundos} segundos antes de volver a intentarlo.";
        }

        return [
            'titulo' => $base['titulo'],
            'mensaje' => $mensaje,
            'sugerencia' => $status === 403 ? $this->sugerenciaPorRol($request) : null,
        ];
    }

    /**
     * Mensaje escrito por nosotros en abort(403, '...') o Response::deny('...').
     * Los textos por defecto del framework se descartan.
     */
    private function mensajePersonalizado(int $status, Throwable $e): ?string
    {
        if ($status >= 500 || ! $e instanceof HttpExceptionInterface) {
            return null;
        }

        $mensaje = trim($e->getMessage());

        if ($mensaje === '') {
            return null;
        }

        foreach (self::MENSAJES_TECNICOS as $patron) {
            if (preg_match($patron, $mensaje)) {
                return null;
            }
        }

        return $mensaje;
    }

    private function segundosDeEspera(Throwable $e): ?int
    {
        if (! $e instanceof HttpExceptionInterface) {
            return null;
        }

        $segundos = (int) ($e->getHeaders()['Retry-After'] ?? 0);

        return $segundos > 0 ? $segundos : null;
    }

    private function sugerenciaPorRol(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return 'Inicia sesión con una cuenta que tenga acceso a esta sección.';
        }

        return match (true) {
            $user->hasRole('super_admin') => 'Esta sección está pensada para otro tipo de usuario.',
            $user->hasRole('admin') => 'Tu rol de administrador no incluye este permiso. Si lo necesitas, solicítalo al super administrador.',
            $user->hasRole('integrante') => 'Esta sección está reservada para la administración o para integrantes de otro consejo.',
            $user->hasRole('invitado') => 'Tu cuenta de invitado tiene acceso limitado. Una vez que tu postulación sea aprobada podrás acceder a más secciones del sistema.',
            default => 'Por favor regresa a la página anterior.',
        };
    }
}
