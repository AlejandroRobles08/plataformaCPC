<?php

namespace App\Policies;

use App\Models\Legalidad;
use App\Models\User;

class PeriodoPolicy
{
    //determina si el usuario puede acceder al módulo de periodos.
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('integrante');
    }

    //dtermina si el usuario puede consultar un periodo específico.
    public function view(User $user, Legalidad $periodo): bool
    {
        // Super admin
        if ($user->hasRole('super_admin')) {
            return true;
        }
        // Admin
        if (
            $user->hasRole('admin')
            && $user->can('periodos.ver')) {
            return true;
        }
        // Integrante: únicamente puede consultar su propio periodo.
        return $user->hasRole('integrante')
            && $user->can('periodos.ver')
            && $user->integrante?->id === $periodo->integrante_id;
    }

    //dtermina si el usuario puede crear un periodo.
    public function create(User $user): bool
    {
        return ($user->hasRole('admin') || $user->hasRole('super_admin'))
            && $user->can('periodos.crear');
    }

    //dtermina si el integrante puede solicitar una reelección únicamente sobre su propio periodo.
    public function solicitarReeleccion(User $user,Legalidad $periodo): bool 
    {
        return $user->hasRole('integrante')
            && $user->can('periodos.solicitar_reeleccion')
            && $user->integrante?->id === $periodo->integrante_id;
    }

    //determina si el usuario puede validar una solicitud de reelección.
    public function validarReeleccion(User $user, Legalidad $periodo): bool 
    {
        return ($user->hasRole('admin') || $user->hasRole('super_admin'))
            && $user->can('periodos.validar_reeleccion');
    }

    // Determina si el usuario puede rechazar una solicitud de reelección.
    public function rechazarReeleccion(User $user, Legalidad $periodo): bool 
    {
        return ($user->hasRole('admin') || $user->hasRole('super_admin'))
            && $user->can('periodos.rechazar_reeleccion');
    }

    // Determina si el usuario puede consultar los documentos de algun periodo
    public function viewDocuments(User $user,Legalidad $periodo): bool 
    {
        // Super admin
        if ($user->hasRole('super_admin')) {
            return true;
        }
        // Admin
        if (
            $user->hasRole('admin')
            && $user->can('periodos.documentos.ver')) {
            return true;
        }

        //integrante: únicamente sus propios documentos.
        return $user->hasRole('integrante')
            && $user->can('periodos.documentos.ver')
            && $user->integrante?->id === $periodo->integrante_id;
    }

    // Determina si el usuario puede subir documentos únicamente a su propio periodo.
    public function subirDocumentos(User $user, Legalidad $periodo): bool 
    {
        return $user->hasRole('integrante')
            && $user->can('periodos.documentos.subir')
            && $user->integrante?->id === $periodo->integrante_id;
    }
}