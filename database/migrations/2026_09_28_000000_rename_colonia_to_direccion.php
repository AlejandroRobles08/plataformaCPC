<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tablas = ['integrantes', 'postulaciones'];

    public function up(): void {
        foreach ($this->tablas as $tabla) {
            // Renombrar colonia a direccion
            Schema::table($tabla, function (Blueprint $table) {
                $table->renameColumn('colonia', 'direccion');
            });

            // Ampliar longitud para soportar una dirección completa
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('direccion', 500)->nullable()->change();
            });
        }
    }

    public function down(): void {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->string('direccion', 255)->nullable()->change();
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->renameColumn('direccion', 'colonia');
            });
        }
    }
};
