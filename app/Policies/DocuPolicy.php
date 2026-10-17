<?php

namespace App\Policies;

use App\Models\Docu;
use App\Models\Integrante;
use App\Models\User;

// Reglas de acceso al expediente de documentos de un integrante.
// El super_admin pasa todas las reglas por el Gate::before de AuthServiceProvider.
class DocuPolicy
{
    //determina si el usuario puede consultar el expediente de un integrante
    public function viewAny(User $user, Integrante $integrante): bool
    {
        if (! $user->can('documentos.ver')) {
            return false;
        }

        return $user->hasRole('admin') || $this->esPropio($user, $integrante->id);
    }

    //determina si el usuario puede ver o descargar un documento específico
    public function view(User $user, Docu $documento): bool
    {
        if (! $user->can('documentos.ver')) {
            return false;
        }

        return $user->hasRole('admin') || $this->esPropio($user, $documento->integrante_id);
    }

    //únicamente el propio integrante sube documentos a su expediente
    public function create(User $user, Integrante $integrante): bool
    {
        return $user->hasRole('integrante')
            && $user->can('documentos.subir')
            && $this->esPropio($user, $integrante->id);
    }

    //determina si el usuario puede aprobar un documento
    public function aprobar(User $user, Docu $documento): bool
    {
        return $user->hasRole('admin') && $user->can('documentos.aprobar');
    }

    //determina si el usuario puede rechazar un documento con observaciones
    public function rechazar(User $user, Docu $documento): bool
    {
        return $user->hasRole('admin') && $user->can('documentos.observar');
    }

    private function esPropio(User $user, int $integranteId): bool
    {
        return $user->hasRole('integrante')
            && $user->integrante?->id === $integranteId;
    }
}
