<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca cuándo el integrante terminó de capturar sus datos.
     * Los integrantes que el admin crea con datos mínimos quedan en null
     * hasta que ellos mismos usan "Darme de alta".
     */
    public function up(): void
    {
        Schema::table('integrantes', function (Blueprint $table) {
            $table->timestamp('alta_completada_at')->nullable()->after('formula');
            // El registro preliminar solo tiene nombre; los apellidos los captura el integrante
            $table->string('apellido_paterno')->nullable()->change();
        });

        // Los integrantes existentes ya tienen sus datos capturados
        DB::table('integrantes')->update(['alta_completada_at' => now()]);
    }

    public function down(): void
    {
        DB::table('integrantes')->whereNull('apellido_paterno')->update(['apellido_paterno' => '']);

        Schema::table('integrantes', function (Blueprint $table) {
            $table->dropColumn('alta_completada_at');
            $table->string('apellido_paterno')->nullable(false)->change();
        });
    }
};
