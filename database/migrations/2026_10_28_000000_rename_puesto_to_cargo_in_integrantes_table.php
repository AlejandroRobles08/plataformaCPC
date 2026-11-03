<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El cargo del integrante solo puede ser Persona Consejera Titular o Suplente
     * (ver Integrante::CARGOS); la validación se hace en IntegranteController.
     */
    public function up(): void
    {
        Schema::table('integrantes', function (Blueprint $table) {
            $table->renameColumn('puesto', 'cargo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrantes', function (Blueprint $table) {
            $table->renameColumn('cargo', 'puesto');
        });
    }
};
