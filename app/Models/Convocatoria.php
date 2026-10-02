<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Convocatoria extends Model
{
    use HasFactory;

    protected $fillable = [
        'consejo_id',
        'tipo_sesion',
        'fecha',
        'convocatoria_documento',
        'documento',
        'lista_asistencia',
        'evidencia',
        'estado_convocatoria',  
        'estado_sesion',
    ];

    public function consejo() {
        return $this->belongsTo(Consejo::class);
    }
}
