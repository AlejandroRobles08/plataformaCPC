<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La persona escribe su correo dos veces para evitar errores de captura.
     * Las postulaciones anteriores quedan sin confirmación (null).
     */
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->string('correo_confirmacion')->nullable()->after('correo');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('correo_confirmacion');
        });
    }
};
