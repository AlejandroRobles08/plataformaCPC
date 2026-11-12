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

    // Pendientes del segundo filtro: ya tienen documentos y esperan aprobación final
    public const ESTATUS_POR_VALIDAR = ['pendiente', 'pre_aprobada'];

    // Nombres legibles de los documentos (mismos que ve la persona en el formulario)
    public const NOMBRES_DOCUMENTOS = [
        'ine' => 'INE',
        'comprobante_domiciliario' => 'Comprobante domiciliario',
        'bajo_protesta' => 'Bajo protesta art. 170',
        'integracion_formula' => 'Integración de fórmula',
        'curriculum_vitae' => 'Currículum Vitae',
        'carta_motivos' => 'Carta de motivos',
        'cumplimiento_normatividad' => 'Cumplimiento de normatividad',
    ];

    public const GENEROS = ['Mujer', 'Hombre', 'Otro', 'Prefiero no responder'];

    // Perfil con el que la persona se postula (primer filtro, ruta pública)
    public const PERFILES = [
        'Ciudadanía en general',
        'Representación vecinal o comité de colonia',
        'Juventudes (18 a 29 años)',
        'Sector académico o de investigación',
        'Organización de la sociedad civil',
        'Sector empresarial o productivo',
        'Salud (medicina, enfermería, psicología u otra área)',
        'Seguridad, prevención o protección civil',
        'Derecho y derechos humanos',
        'Arquitectura, ingeniería o urbanismo',
        'Arte, cultura o gestión cultural',
        'Persona artesana o productora local',
        'Medio ambiente y sustentabilidad',
        'Bienestar animal o medicina veterinaria',
        'Inclusión de personas con discapacidad',
        'Igualdad de género y derechos de las mujeres',
        'Niñez y adolescencia (educación, trabajo social u otra área)',
        'Otro',
    ];

    protected $fillable = [
        'nombre',
        'user_id',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'direccion',
        'correo',
        'correo_confirmacion',
        'consejo_solicitado_id',
        'perfil',
        'perfil_descripcion',
        'consejo_id',
        'formula',
        'acta_resolucion',
        'estatus',
        'comentarios_revision',
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

    // Postulación abierta aún sin revisar (todavía no tiene cuenta de acceso)
    public function enPrimerFiltro(): bool
    {
        return $this->estatus === 'pendiente' && $this->user_id === null;
    }

    // Pre-aprobada y con acceso creado, pero sin documentos cargados
    public function esperaDocumentos(): bool
    {
        return $this->estatus === 'pre_aprobada' && $this->documentos()->doesntExist();
    }

    public function documentos(){
        return $this->hasMany(PostulacionDocumento::class);
    }

    // Postulaciones en el segundo filtro con documentos que esperan revisión
    // (alerta roja del botón "Panel de validación")
    public function scopeConDocumentosPorRevisar($query)
    {
        return $query->whereIn('estatus', self::ESTATUS_POR_VALIDAR)
            ->whereHas('documentos', fn ($q) => $q->where('estatus', PostulacionDocumento::PENDIENTE));
    }

    // Expediente completo: todos los documentos requeridos están cargados y aprobados
    public function expedienteAprobado(): bool
    {
        $aprobados = $this->documentos()
            ->where('estatus', PostulacionDocumento::APROBADO)
            ->pluck('tipo')
            ->all();

        return empty(array_diff(self::TIPOS_DOCUMENTOS, $aprobados));
    }
}
