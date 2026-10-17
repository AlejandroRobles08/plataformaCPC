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
use Illuminate\Validation\ValidationException;


class Integrante extends Model
{
    use HasFactory;

    public const MAX_FORMULAS = 15;
    public const INTEGRANTES_POR_FORMULA = 2;
    
    protected $fillable = [
        'user_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'direccion',
        'discapacidad',
        'discapacidad_tipo',
        'puesto',
        'correo',
        'consejo_id',
        'formula'
    ];

    // Apellidos completos (paterno + materno) para mostrar en vistas y reportes
    protected $appends = ['apellido'];

    public function getApellidoAttribute(): string
    {
        return trim($this->apellido_paterno . ' ' . $this->apellido_materno);
    }

    //relacion user - integrante: un integrante pertenece a un usuario
    public function user(): BelongsTo{
        return $this->belongsTo(User::class);
    }
    
    //relacion un integrante pertenece a un consejo
    public function consejo(): BelongsTo
    {
        return $this->belongsTo(Consejo::class);
    }
    
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

    // Fórmula que corresponde al siguiente integrante según el orden de alta:
    // se llenan en grupos de 2 y los lugares liberados por bajas se respetan.
    // Debe llamarse dentro de una transacción con el consejo bloqueado (lockForUpdate).
    public static function siguienteFormula(int $consejoId): int
    {
        $ultima = static::where('consejo_id', $consejoId)->max('formula');

        if ($ultima === null) {
            return 1;
        }

        $ultima = (int) $ultima;

        $ocupados = static::where('consejo_id', $consejoId)
            ->where('formula', $ultima)
            ->count();

        $siguiente = $ocupados >= self::INTEGRANTES_POR_FORMULA ? $ultima + 1 : $ultima;

        if ($siguiente > self::MAX_FORMULAS) {
            throw ValidationException::withMessages([
                'formula' => 'El consejo ya tiene las ' . self::MAX_FORMULAS . ' fórmulas completas ('
                    . self::MAX_FORMULAS * self::INTEGRANTES_POR_FORMULA . ' integrantes).',
            ]);
        }

        return $siguiente;
    }
}