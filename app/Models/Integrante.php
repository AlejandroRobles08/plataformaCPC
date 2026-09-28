<?php

namespace App\Models;

use App\Models\Asistencia;
use App\Models\Consejo;
use App\Models\Legalidad;
use App\Models\IntegranteBaja;
use App\Models\Docu;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Integrante extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'nombre',
        'apellido',
        'genero',
        'direccion',
        'discapacidad',
        'discapacidad_tipo',
        'puesto',
        'correo',
        'consejo_id',
        'formula'
    ];

    //relacion user - integrante: un integrante pertenece a un usuario
    public function user(): BelongsTo{
        return $this->belongsTo(User::class);
    }
    
    //relacion un integrane pertenece a varios consejos
    public function consejo(){
        return $this->belongsToMany(Consejo::class);
   }
   //relación muchos a muchos con consejos a través de la tabla de apoyo
    
    public function documentos() 
    {
        return $this->hasMany(Docu::class,);
    }
    //relacion con asistencias: un integrante tiene muchas asistencias
    public function asistencias(){
        return $this->hasMany(Asistencia::class);
    }    
    //para legalidad
    public function legalidad()
    {
        return $this->hasMany(Legalidad::class);    
    }
    //para reportes de bajas
    public function baja(){
        return $this->hasOne(IntegranteBaja::class);
    }    
}