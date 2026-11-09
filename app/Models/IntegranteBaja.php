<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegranteBaja extends Model
{
    use HasFactory;

    protected $table = 'integrante_bajas';

    protected $fillable = [
        'integrante_id',
        'user_id',
        'consejo_id',
        'nombre',
        'apellido',
        'motivo',
        'fecha_baja',
        'evidencia_pdf',
    ];

    protected $casts = [
        'fecha_baja' => 'date',
    ];

    // Relaciones
    public function integrante(){
        return $this->belongsTo(Integrante::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function consejo(){
        return $this->belongsTo(Consejo::class);
    }

    // Datos que ve la persona en el aviso de cuenta desactivada.
    // Un error de registro no es una baja real: se muestra un aviso genérico, sin motivo ni detalles.
    public function datosAviso(): array
    {
        if ($this->motivo === 'error_registro') {
            return ['motivo' => null, 'fecha_baja' => null, 'consejo' => null];
        }

        return [
            'motivo' => $this->motivo,
            'fecha_baja' => $this->fecha_baja?->toDateString(),
            'consejo' => $this->consejo?->nombre,
        ];
    }
}
