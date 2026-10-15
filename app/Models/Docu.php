<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Docu extends Model
{
    use HasFactory;

    // Disco privado: los documentos no son accesibles por URL directa,
    // solo a través de DocuController (que aplica DocuPolicy).
    public const DISCO = 'local';

    public const TIPOS = [
        'ine',
        'comprobante_domiciliario',
        'bajo_protesta_art_170',
        'integracion_formula',
        'curriculum_vitae',
        'carta_motivos',
        'cumplimiento_normatividad',
    ];

    // Tipos que la postulación nombra distinto al expediente del integrante
    public const TIPOS_DESDE_POSTULACION = [
        'bajo_protesta' => 'bajo_protesta_art_170',
    ];

    public const PENDIENTE = 'pendiente';
    public const APROBADO = 'aprobado';
    public const RECHAZADO = 'rechazado';

    protected $fillable = [
        'integrante_id',
        'tipo',
        'archivo',
        'estatus',
        'observacion',
        'validado_por',
        'validado_at',
    ];

    // La ruta física nunca se envía al navegador
    protected $hidden = ['archivo'];

    protected function casts(): array
    {
        return [
            'validado_at' => 'datetime',
        ];
    }

    public function integrante(): BelongsTo
    {
        return $this->belongsTo(Integrante::class);
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public static function carpetaDe(int $integranteId): string
    {
        return "documentos/{$integranteId}";
    }

    public static function disco()
    {
        return Storage::disk(self::DISCO);
    }

    public function existeArchivo(): bool
    {
        return $this->archivo && self::disco()->exists($this->archivo);
    }

    // Un documento aprobado queda bloqueado; uno pendiente espera revisión.
    // Solo se puede reemplazar cuando fue rechazado.
    public function puedeReemplazarse(): bool
    {
        return $this->estatus === self::RECHAZADO;
    }

    // Un documento aprobado ya no se vuelve a validar
    public function puedeValidarse(): bool
    {
        return $this->estatus !== self::APROBADO;
    }

    // Nombre legible para descargas: "INE - Juan Pérez.pdf"
    public function nombreDescarga(): string
    {
        $tipo = str_replace('_', ' ', ucfirst($this->tipo));
        $integrante = $this->integrante;
        $nombre = $integrante
            ? trim($integrante->nombre . ' ' . $integrante->apellido)
            : 'integrante';

        return "{$tipo} - {$nombre}.pdf";
    }
}
