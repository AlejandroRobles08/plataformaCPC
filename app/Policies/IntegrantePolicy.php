<?php

namespace App\Policies;

use App\Models\Integrante;
use App\Models\User;
use App\Models\Consejo;

class IntegrantePolicy
{
    //determina si el usuario puede ver la lista de integrantes
    public function viewAny(User $user, Consejo $consejo): bool
    {
        //los admins pueden cosnultar cualquier consejo y sus integrantess
       if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        //un integrante sólo puede acceder a su propio consejo
        return $user->hasRole('integrante') && 
        $user->integrante?->consejo_id === $consejo->id;
    }

    //determina si el usuario puede ver un integrante específico
    public function view(User $user, Integrante $integrante): bool
    {
        // Administradores pueden consultar integrantes.
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Un integrante sólo puede consultar su propio registro.
        return $user->hasRole('integrante')
            && $user->integrante?->id === $integrante->id;
    }

    //Determina si el usuario puede crear integrantes
    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    //determina si el usuario puede actualizar un integrante
    public function update(User $user, Integrante $integrante): bool
    {
        // Administradores pueden actualizar integrantes.
        if ($user->hasRole('admin') || $user->hasRole('super_admin')) {
            return true;
        }

        // Un integrante únicamente puede actualizarse a sí mismo.
        return $user->hasRole('integrante')
            && $user->integrante?->id === $integrante->id;
    }

    //Determina si el usuario puede eliminar un integrante.
    public function delete(User $user, Integrante $integrante): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    //determina si el usuario puede restaurar un integrante.
    public function restore(User $user, Integrante $integrante): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    //determina| si el usuario puede eliminar permanentemente un integrante.
    public function forceDelete(User $user, Integrante $integrante): bool
    {
        return $user->hasRole('super_admin');
    }
}