<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        // únicamente el super_admin puede consultar roles y permisos
        $verRoles = Auth::user()->hasRole('super_admin');

        $users = User::query()
            ->when($verRoles, fn ($query) => $query->with(['roles.permissions', 'permissions']))
            ->orderBy('name')
            ->paginate()
            ->through(function (User $user) use ($verRoles) {
                $datos = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];

                if ($verRoles) {
                    $datos['roles'] = $user->roles->pluck('name')->values();
                    $datos['permisos'] = $user->getAllPermissions()->pluck('name')->sort()->values();
                }

                return $datos;
            });

        return Inertia::render('Users/Index', [
            'users' => $users,
            'verRoles' => $verRoles,
            // catálogo de roles registrados en la base de datos con sus permisos
            'roles' => $verRoles
                ? Role::with('permissions')->orderBy('name')->get()->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permisos' => $role->permissions->pluck('name')->sort()->values(),
                ])
                : [],
        ]);
    }
}
