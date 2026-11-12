<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Ícono (Material Symbols) para los consejos que ya existían antes de poder elegirlo desde el formulario
    private const ICONOS_POR_NOMBRE = [
        'asuntos indígenas' => 'diversity_2',
        'bienestar' => 'self_improvement',
        'bienestar animal' => 'pets',
        'centro histórico y patrimonio edificado' => 'account_balance',
        'cultura' => 'theater_comedy',
        'deporte' => 'sports_soccer',
        'derechos humanos e igualdad entre géneros' => 'balance',
        'desarrollo urbano' => 'location_city',
        'desempeño gubernamental' => 'assured_workload',
        'discapacidad' => 'accessible_forward',
        'ecología y medio ambiente' => 'eco',
        'educación' => 'school',
        'juventud' => 'emoji_people',
        'niñez y adolescencia' => 'child_care',
        'niñez y la adolescencia' => 'child_care',
        'obras y servicios públicos' => 'construction',
        'movilidad' => 'commute',
        'personas en situación de vulnerabilidad' => 'volunteer_activism',
        'protección civil' => 'crisis_alert',
        'salud' => 'medical_services',
        'seguridad pública' => 'local_police',
        'turismo' => 'luggage',
        'vialidad y transporte' => 'directions_bus',
        'impulso a las artesanías' => 'palette',
        'igualdad de género, y prevención, atención y erradicación de la violencia contra la mujer.' => 'female',
    ];

    public function up(): void
    {
        Schema::table('consejos', function (Blueprint $table) {
            $table->string('icono', 50)->nullable()->after('descripcion');
        });

        DB::table('consejos')->select('id', 'nombre')->orderBy('id')->each(function ($consejo) {
            // colapsa espacios y saltos de línea para que nombres largos coincidan con su llave
            $nombre = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $consejo->nombre)));

            if ($icono = self::ICONOS_POR_NOMBRE[$nombre] ?? null) {
                DB::table('consejos')->where('id', $consejo->id)->update(['icono' => $icono]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('consejos', function (Blueprint $table) {
            $table->dropColumn('icono');
        });
    }
};
