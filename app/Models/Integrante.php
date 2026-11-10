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
    // Fórmulas completas que necesita un consejo para considerarse instalado
    public const MIN_FORMULAS = 10;
    public const INTEGRANTES_POR_FORMULA = 2;
    public const CARGOS = ['Persona Consejera Titular', 'Persona Consejera Suplente'];
    // Faltas seguidas (sin importar el mes) que causan baja y sacan al integrante del pase de lista
    public const FALTAS_CONSECUTIVAS_BAJA = 3;
    
    protected $fillable = [
        'user_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'genero',
        'direccion',
        'discapacidad',
        'discapacidad_tipo',
        'cargo',
        'correo',
        'consejo_id',
        'formula',
        'alta_completada_at',
    ];

    protected $casts = [
        'alta_completada_at' => 'datetime',
    ];

    // Cada alta, baja o cambio de fórmula recalcula si el consejo está instalado
    protected static function booted(): void
    {
        $sincronizar = function (Integrante $integrante) {
            $ids = array_unique(array_filter([
                $integrante->consejo_id,
                $integrante->getOriginal('consejo_id'),
            ]));
            Consejo::whereKey($ids)->get()->each->sincronizarInstalacion();
        };

        static::created($sincronizar);
        static::deleted($sincronizar);
        static::updated(function (Integrante $integrante) use ($sincronizar) {
            if ($integrante->wasChanged(['consejo_id', 'formula'])) {
                $sincronizar($integrante);
            }
        });
    }

    // Apellidos completos (paterno + materno) y nombre completo para mostrar en vistas y reportes
    protected $appends = ['apellido', 'nombre_completo'];

    public function getApellidoAttribute(): string
    {
        return trim($this->apellido_paterno . ' ' . $this->apellido_materno);
    }

    // Une nombre(s) + apellido paterno + apellido materno sin dejar espacios de más si falta alguna parte
    public static function formatearNombreCompleto(?string $nombre, ?string $paterno, ?string $materno): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', [$nombre, $paterno, $materno])));
    }

    // $integrante->nombre_completo (y en Vue: integrante.nombre_completo)
    public function getNombreCompletoAttribute(): string
    {
        return static::formatearNombreCompleto($this->nombre, $this->apellido_paterno, $this->apellido_materno);
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
    // Expediente completo: todos los tipos de Docu::TIPOS subidos y aprobados.
    // Sin él, el integrante no puede usar los demás módulos ni se le registran periodos o asistencias.
    public function expedienteAprobado(): bool
    {
        return static::idsSinExpedienteAprobado([$this->id])->isEmpty();
    }

    // Ids (de los recibidos) a los que les falta al menos un documento aprobado
    public static function idsSinExpedienteAprobado(iterable $ids): \Illuminate\Support\Collection
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $completos = Docu::whereIn('integrante_id', $ids)
            ->whereIn('tipo', Docu::TIPOS)
            ->where('estatus', Docu::APROBADO)
            ->groupBy('integrante_id')
            ->havingRaw('COUNT(DISTINCT tipo) >= ?', [count(Docu::TIPOS)])
            ->pluck('integrante_id')
            ->map(fn ($id) => (int) $id);

        return $ids->diff($completos)->values();
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

    // Semáforo de asistencia a partir de los estados del integrante, ordenados por sesión:
    // verde sin faltas, amarillo con faltas y rojo con una racha de 3 faltas seguidas.
    // Una asistencia o una falta justificada interrumpe la racha; las sesiones sin registro no cuentan.
    public static function colorSemaforo(iterable $estados): string
    {
        $color = 'verde';
        $racha = 0;

        foreach ($estados as $estado) {
            $racha = $estado === 'falto' ? $racha + 1 : 0;

            if ($racha >= self::FALTAS_CONSECUTIVAS_BAJA) {
                return 'rojo';
            }

            if ($racha > 0) {
                $color = 'amarillo';
            }
        }

        return $color;
    }

    // Color del semáforo de cada integrante, indexado por id
    public static function semaforosAsistencia(iterable $ids): \Illuminate\Support\Collection
    {
        $ids = collect($ids);

        $estados = Asistencia::whereIn('integrante_id', $ids)
            ->orderBy('fecha')
            ->orderBy('sesion_id')
            ->orderBy('id')
            ->get(['integrante_id', 'estado'])
            ->groupBy('integrante_id');

        return $ids->mapWithKeys(fn ($id) => [
            $id => static::colorSemaforo($estados->get($id, collect())->pluck('estado')),
        ]);
    }

    // Integrantes en rojo: no se les pasa lista hasta que se apruebe alguno de sus justificantes
    public static function idsSinPaseDeLista(iterable $ids): \Illuminate\Support\Collection
    {
        return static::semaforosAsistencia($ids)
            ->filter(fn ($color) => $color === 'rojo')
            ->keys();
    }

    // Primera fórmula con lugar libre (de la 1 a la 15), o null si el consejo está lleno.
    // Los lugares liberados por bajas se vuelven a ocupar antes de abrir una fórmula nueva.
    public static function formulaDisponible(int $consejoId): ?int
    {
        $ocupados = static::where('consejo_id', $consejoId)
            ->whereNotNull('formula')
            ->groupBy('formula')
            ->selectRaw('formula, COUNT(*) as total')
            ->pluck('total', 'formula');

        for ($formula = 1; $formula <= self::MAX_FORMULAS; $formula++) {
            if (($ocupados[$formula] ?? 0) < self::INTEGRANTES_POR_FORMULA) {
                return $formula;
            }
        }

        return null;
    }

    // Cuántas fórmulas completas (con sus 2 integrantes) tiene cada consejo, indexado por consejo_id
    public static function formulasCompletasPorConsejo(?int $consejoId = null): \Illuminate\Support\Collection
    {
        return static::query()
            ->fromSub(
                static::whereNotNull('formula')
                    ->when($consejoId, fn ($query) => $query->where('consejo_id', $consejoId))
                    ->groupBy('consejo_id', 'formula')
                    ->havingRaw('COUNT(*) >= ?', [self::INTEGRANTES_POR_FORMULA])
                    ->select('consejo_id'),
                'completas'
            )
            ->groupBy('consejo_id')
            ->selectRaw('consejo_id, COUNT(*) as total')
            ->pluck('total', 'consejo_id');
    }

    // Lanza un error de validación si la fórmula ya tiene sus 2 integrantes.
    // Debe llamarse dentro de una transacción con el consejo bloqueado (lockForUpdate).
    public static function asegurarEspacioEnFormula(int $consejoId, int $formula, ?int $exceptoId = null): void
    {
        $ocupados = static::where('consejo_id', $consejoId)
            ->where('formula', $formula)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->count();

        if ($ocupados >= self::INTEGRANTES_POR_FORMULA) {
            throw ValidationException::withMessages([
                'formula' => 'No hay espacio disponible en esta fórmula',
            ]);
        }
    }

    // Lanza un error de validación si otro integrante de la fórmula ya tiene ese cargo:
    // cada fórmula se compone de una persona titular y una suplente.
    // Debe llamarse dentro de una transacción con el consejo bloqueado (lockForUpdate).
    public static function asegurarCargoDisponible(int $consejoId, ?int $formula, ?string $cargo): void
    {
        // Los integrantes aprobados desde una postulación aún no tienen cargo asignado
        if ($formula === null || $cargo === null) {
            return;
        }

        $ocupado = static::where('consejo_id', $consejoId)
            ->where('formula', $formula)
            ->where('cargo', $cargo)
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages([
                'cargo' => "La fórmula {$formula} ya tiene una {$cargo}.",
            ]);
        }
    }

    public static function consejoLleno(int $consejoId): bool
    {
        return static::formulaDisponible($consejoId) === null;
    }

    // Fórmula que corresponde al siguiente integrante.
    // Debe llamarse dentro de una transacción con el consejo bloqueado (lockForUpdate).
    public static function siguienteFormula(int $consejoId): int
    {
        $siguiente = static::formulaDisponible($consejoId);

        if ($siguiente === null) {
            throw ValidationException::withMessages([
                'formula' => 'El consejo ya tiene las ' . self::MAX_FORMULAS . ' fórmulas completas ('
                    . self::MAX_FORMULAS * self::INTEGRANTES_POR_FORMULA . ' integrantes).',
            ]);
        }

        return $siguiente;
    }
}