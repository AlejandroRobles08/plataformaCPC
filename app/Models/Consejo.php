<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consejo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'instalado_at',
        'acta_instalacion',
        'instalado_por',
    ];

    protected $casts = [
        'instalado_at' => 'date',
    ];

    // Se envía a las vistas para pintar la tarjeta según su estado de instalación
    protected $appends = ['instalado'];

    public function getInstaladoAttribute(): bool
    {
        return $this->instalado_at !== null;
    }

    // Un consejo está instalado mientras tenga el mínimo de fórmulas completas:
    // se instala al alcanzarlo y vuelve a "No instalado" si las bajas lo dejan por debajo
    public function sincronizarInstalacion(): void
    {
        $completas = Integrante::formulasCompletasPorConsejo($this->id)->get($this->id, 0);
        $cumpleMinimo = $completas >= Integrante::MIN_FORMULAS;

        if ($cumpleMinimo && ! $this->instalado_at) {
            $this->update([
                'instalado_at' => now()->toDateString(),
                'instalado_por' => auth()->id(),
            ]);
        } elseif (! $cumpleMinimo && $this->instalado_at) {
            $this->update([
                'instalado_at' => null,
                'instalado_por' => null,
            ]);
        }
    }

    public function instaladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instalado_por');
    }

    //relacion con integrantes muchos a muchos
    public function integrantes(){
        return $this->hasMany(Integrante::class);
    }

    //relacion con convocatorias uno a muchos
    public function convocatorias(){
        return $this->hasMany(Convocatoria::class);
    }
    //relacion para las bajas
    public function bajas(){
        return $this->hasMany(IntegranteBaja::class);
    }
    //un cosejo tiene muchas sesiones
    public function sesiones()
    {
        return $this->hasMany(Sesion::class);
    }
}
