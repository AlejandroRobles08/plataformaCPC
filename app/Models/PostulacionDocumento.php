<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulacionDocumento extends Model
{
    // Mismos estados que el archivo digital del integrante
    public const PENDIENTE = 'pendiente';
    public const APROBADO = 'aprobado';
    public const RECHAZADO = 'rechazado';

    protected $fillable = [
        'postulacion_id',
        'tipo',
        'archivo',
        'estatus',
        'observacion',
        'validado_por',
        'validado_at',
    ];

    protected $attributes = [
        'estatus' => self::PENDIENTE,
    ];

    protected $casts = [
        'validado_at' => 'datetime',
    ];

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class);
    }

    public function validador()
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    // Solo se valida un documento en revisión. Flujo:
    // la persona sube (pendiente) -> admin rechaza con observación (rechazado)
    // -> la persona sube una nueva versión (pendiente otra vez) -> ... -> admin aprueba (bloqueado)
    public function puedeValidarse(): bool
    {
        return $this->estatus === self::PENDIENTE;
    }

    // Solo se reemplaza un documento que no fue aprobado
    public function puedeReemplazarse(): bool
    {
        return $this->estatus === self::RECHAZADO;
    }

    public function motivoNoValidable(): string
    {
        return $this->estatus === self::APROBADO
            ? 'El documento ya fue aprobado.'
            : 'El documento no fue aprobado; podrá revisarse cuando la persona suba una nueva versión.';
    }
}
