<?php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';
    /**
     * Determine the current asset version.
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }
    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request)
    {
        return array_merge(parent::share($request), [

            'auth' => [
                'user' => $request->user(),

                'roles' => function () use ($request) {
                    return $request->user()
                        ? $request->user()
                            ->getRoleNames()
                            ->values()
                            ->all()
                        : [];
                },
                // Integrante que aún no termina "Darme de alta": solo ve Archivo digital
                'altaPendiente' => function () use ($request) {
                    $user = $request->user();

                    return (bool) ($user?->hasRole('integrante')
                        && $user->integrante
                        && $user->integrante->alta_completada_at === null);
                },
                'permissions' => function () use ($request) {
                    $user = $request->user();

                    if (! $user) {
                        return [];
                    }

                    // el super_admin recibe todos los permisos existentes
                    if ($user->hasRole('super_admin')) {
                        return \Spatie\Permission\Models\Permission::pluck('name')->values()->all();
                    }

                    return $user->getAllPermissions()->pluck('name')->values()->all();
                },
            ],
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'warning' => $request->session()->get('warning'),
                    'error' => $request->session()->get('error'),
                    // Errores HTTP de acciones (permisos, sesión expirada...), ver FriendlyErrorResponse
                    'errorGlobal' => $request->session()->get('error_global'),
                ];
            },
            'showingMobileMenu' => false,
            'mustChangePassword' =>
                optional($request->user())->must_change_password ?? false,
        ]);
    }
}