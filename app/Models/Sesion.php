<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    use HasFactory;

    protected $table = 'sesiones';
    protected $fillable = [
        'consejo_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'tipo_sesion',
        'estado',
    ];

    //Una sesión pertenece a un consejo.
    public function consejo()
    {
        return $this->belongsTo(Consejo::class);
    }

    //Una sesión puede tener muchas asistencias.
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }
}