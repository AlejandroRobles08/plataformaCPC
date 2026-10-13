<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // tabla => columna actual con los apellidos juntos
    private array $tablas = [
        'integrantes' => 'apellido',
        'postulaciones' => 'apellidos',
    ];

    /**
     * Separa los apellidos en paterno y materno.
     * Los registros existentes se dividen en el primer espacio:
     * la primera palabra es el paterno y el resto el materno.
     */
    public function up(): void
    {
        foreach ($this->tablas as $tabla => $columna) {
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->string('apellido_paterno')->nullable()->after('nombre');
                $table->string('apellido_materno')->nullable()->after('apellido_paterno');
            });

            DB::table($tabla)->select('id', $columna)->orderBy('id')
                ->each(function ($fila) use ($tabla, $columna) {
                    $partes = preg_split('/\s+/', trim((string) $fila->$columna), 2);

                    DB::table($tabla)->where('id', $fila->id)->update([
                        'apellido_paterno' => $partes[0] ?? '',
                        'apellido_materno' => ($partes[1] ?? '') !== '' ? $partes[1] : null,
                    ]);
                });

            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->string('apellido_paterno')->nullable(false)->change();
                $table->dropColumn($columna);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tablas as $tabla => $columna) {
            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->string($columna)->nullable()->after('nombre');
            });

            DB::table($tabla)->update([
                $columna => DB::raw("TRIM(CONCAT(apellido_paterno, ' ', COALESCE(apellido_materno, '')))"),
            ]);

            Schema::table($tabla, function (Blueprint $table) use ($columna) {
                $table->string($columna)->nullable(false)->change();
                $table->dropColumn(['apellido_paterno', 'apellido_materno']);
            });
        }
    }
};
