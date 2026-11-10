<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revisión del primer filtro: la administración puede pre-aprobar una postulación
     * o no aprobarla dejando comentarios. El folio deja de usarse.
     */
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropUnique(['folio']);
            $table->dropColumn('folio');

            $table->enum('estatus', ['pendiente', 'pre_aprobada', 'aprobada', 'no_aprobada'])
                ->default('pendiente')
                ->change();

            $table->text('comentarios_revision')->nullable()->after('estatus');
        });
    }

    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->dropColumn('comentarios_revision');

            $table->enum('estatus', ['pendiente', 'aprobada', 'no_aprobada'])
                ->default('pendiente')
                ->change();

            $table->string('folio', 20)->nullable()->unique()->after('id');
        });
    }
};
