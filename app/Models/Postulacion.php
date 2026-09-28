<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Postulacion extends Model
{
    use HasFactory;

    protected $table = 'postulaciones';

    public const TIPOS_DOCUMENTOS = [
        'ine',
        'comprobante_domiciliario',
        'bajo_protesta',
        'integracion_formula',
        'curriculum_vitae',
        'carta_motivos',
        'cumplimiento_normatividad',
    ];

    protected $fillable = [
        'nombre',
        'user_id',
        'apellidos',
        'genero',
        'direccion',
        'correo',
        'consejo_id',
        'puesto',
        'formula',
        'acta_resolucion',
        'estatus',
        'validado_por',
        'fecha_validacion',
        'fecha_postulacion',
    ];
    
    protected $casts = [
        'formula' => 'integer',
        'fecha_validacion' => 'datetime',
        'fecha_postulacion' => 'datetime',
    ];

    //-----relaciones
    public function user(){
        return $this->belongsTo(User::class);
    }
    
    public function consejo(){
        return $this->belongsTo(Consejo::class);
    }

    public function validador(){
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function documentos(){
        return $this->hasMany(PostulacionDocumento::class);
    }
}
