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
                'permissions' => function () use ($request) {
                    return $request->user()
                        ? $request->user()
                            ->getAllPermissions()
                            ->pluck('name')
                            ->values()
                            ->all()
                        : [];
                },
            ],
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'warning' => $request->session()->get('warning'),
                    'error' => $request->session()->get('error'),
                ];
            },
            'showingMobileMenu' => false,
            'privacyAccepted' =>
                optional($request->user())->privacy_accepted ?? false,
            'mustChangePassword' =>
                optional($request->user())->must_change_password ?? false,
        ]);
    }
}