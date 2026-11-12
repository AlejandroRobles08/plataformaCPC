<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consejo extends Model
{
    use HasFactory;

    // Ícono por defecto para los consejos que no tienen uno asignado
    public const ICONO_DEFAULT = 'group_work';

    // Catálogo de íconos (Material Symbols) que se pueden elegir al agregar un consejo: nombre => etiqueta
    public const ICONOS = [
        'diversity_2' => 'Pueblos indígenas / diversidad',
        'self_improvement' => 'Bienestar',
        'pets' => 'Bienestar animal',
        'account_balance' => 'Patrimonio edificado',
        'theater_comedy' => 'Cultura',
        'sports_soccer' => 'Deporte',
        'balance' => 'Derechos humanos',
        'location_city' => 'Desarrollo urbano',
        'assured_workload' => 'Gobierno',
        'accessible_forward' => 'Discapacidad',
        'eco' => 'Ecología',
        'school' => 'Educación',
        'emoji_people' => 'Juventud',
        'child_care' => 'Niñez',
        'construction' => 'Obras',
        'commute' => 'Movilidad',
        'volunteer_activism' => 'Grupos vulnerables',
        'crisis_alert' => 'Protección civil',
        'medical_services' => 'Salud',
        'local_police' => 'Seguridad pública',
        'luggage' => 'Turismo',
        'directions_bus' => 'Transporte',
        'traffic' => 'Vialidad',
        'palette' => 'Artesanías / arte',
        'female' => 'Mujeres',
        'diversity_3' => 'Igualdad / inclusión',
        'elderly' => 'Personas mayores',
        'family_restroom' => 'Familia',
        'home_work' => 'Vivienda',
        'storefront' => 'Comercio',
        'work' => 'Empleo',
        'agriculture' => 'Campo',
        'water_drop' => 'Agua',
        'park' => 'Parques',
        'recycling' => 'Reciclaje',
        'science' => 'Ciencia',
        'computer' => 'Tecnología',
        'menu_book' => 'Lectura',
        'music_note' => 'Música',
        'handshake' => 'Colaboración',
        'gavel' => 'Justicia',
        'record_voice_over' => 'Participación ciudadana',
        'public' => 'Asuntos internacionales',
        'group_work' => 'General',
    ];

    protected $fillable = [
        'nombre',
        'descripcion',
        'icono',
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
