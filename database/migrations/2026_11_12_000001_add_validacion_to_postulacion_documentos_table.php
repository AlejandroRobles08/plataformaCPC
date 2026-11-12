<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Validación documento por documento del expediente de la postulación
     * (mismo flujo que el archivo digital del integrante): pendiente -> aprobado / rechazado,
     * y un documento rechazado vuelve a pendiente cuando la persona sube una nueva versión.
     */
    public function up(): void
    {
        Schema::table('postulacion_documentos', function (Blueprint $table) {
            $table->enum('estatus', ['pendiente', 'aprobado', 'rechazado'])
                ->default('pendiente')
                ->after('archivo');
            $table->text('observacion')->nullable()->after('estatus');
            $table->foreignId('validado_por')
                ->nullable()
                ->after('observacion')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('validado_at')->nullable()->after('validado_por');
        });
    }

    public function down(): void
    {
        Schema::table('postulacion_documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validado_por');
            $table->dropColumn(['estatus', 'observacion', 'validado_at']);
        });
    }
};
