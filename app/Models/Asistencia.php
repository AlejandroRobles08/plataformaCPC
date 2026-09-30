<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Asistencia extends Model
{
    use HasFactory;

    protected $fillable = [
        'sesion_id',
        'integrante_id',
        'mes',
        'tipo_sesion',
        //'asistio',
        'estado',
        'evidencia',
        'justificante',
        'estado_justificante',
        'comentario_justificante',
        'comentario_visto_at',
        'fecha',
    ];

    //Relación: una asistencia pertenece a un integrante
    public function integrante()
    {
        return $this->belongsTo(Integrante::class);
    }

    //Relación: una asistencia pertenece a una convocatoria
    public function convocatoria()
    {
        return $this->belongsTo(Convocatoria::class);
    }     
    //una asistencia pertenece a una sesión
    public function sesion(){
        return $this->belongsTo(Sesion::class);
    }  
}
