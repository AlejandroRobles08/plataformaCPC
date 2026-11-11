<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Última vez que el integrante abrió su Archivo digital: las revisiones posteriores
    // (aprobados / no aprobados) se le avisan en el botón "Documentos"
    public function up(): void
    {
        Schema::table('integrantes', function (Blueprint $table) {
            $table->timestamp('revisiones_vistas_at')->nullable()->after('alta_completada_at');
        });
    }

    public function down(): void
    {
        Schema::table('integrantes', function (Blueprint $table) {
            $table->dropColumn('revisiones_vistas_at');
        });
    }
};
