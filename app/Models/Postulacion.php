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

    // Valores que se guardan en BD; la vista puede mostrar una etiqueta distinta
    public const GENEROS = ['Mujer', 'Hombre', 'No binario', 'Otro', 'Prefiero no responder'];

    // Perfil con el que la persona se postula (primer filtro, ruta pública)
    public const PERFILES = [
        'Ciudadanía en general',
        'Sector académico o de investigación',
        'Sector empresarial o productivo',
        'Organización de la sociedad civil',
        'Colegio o asociación de profesionistas',
        'Juventudes (18 a 29 años)',
        'Otro',
    ];

    protected $fillable = [
        'folio',
        'nombre',
        'user_id',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'direccion',
        'correo',
        'consejo_solicitado_id',
        'perfil',
        'perfil_descripcion',
        'consejo_id',
        'formula',
        'acta_resolucion',
        'estatus',
        'validado_por',
        'fecha_validacion',
        'fecha_postulacion',
        'aviso_privacidad_at',
    ];
    
    protected $casts = [
        'fecha_validacion' => 'datetime',
        'fecha_postulacion' => 'datetime',
        'aviso_privacidad_at' => 'datetime',
    ];

    //-----relaciones
    public function user(){
        return $this->belongsTo(User::class);
    }
    
    public function validador(){
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function consejo(){
        return $this->belongsTo(Consejo::class);
    }

    public function consejoSolicitado(){
        return $this->belongsTo(Consejo::class, 'consejo_solicitado_id');
    }

    // Folio legible para que la persona dé seguimiento: CPC-2026-00042
    public static function generarFolio(int $id): string
    {
        return sprintf('CPC-%s-%05d', now()->format('Y'), $id);
    }

    public function documentos(){
        return $this->hasMany(PostulacionDocumento::class);
    }
}
