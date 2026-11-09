<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primer filtro de postulaciones: la ciudadanía se postula desde una ruta pública,
     * sin cuenta ni documentos. Se registra el consejo de su interés, su perfil
     * y un folio para que pueda dar seguimiento a su solicitud.
     */
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->string('folio', 20)->nullable()->unique()->after('id');

            // Consejo que la persona eligió; consejo_id sigue siendo el que se asigna al aprobar
            $table->foreignId('consejo_solicitado_id')->nullable()->after('correo')
                ->constrained('consejos')->nullOnDelete();

            $table->string('perfil')->nullable()->after('consejo_solicitado_id');
            $table->text('perfil_descripcion')->nullable()->after('perfil');

            $table->timestamp('aviso_privacidad_at')->nullable()->after('fecha_postulacion');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consejo_solicitado_id');
            $table->dropUnique(['folio']);
            $table->dropColumn(['folio', 'perfil', 'perfil_descripcion', 'aviso_privacidad_at']);
        });
    }
};
