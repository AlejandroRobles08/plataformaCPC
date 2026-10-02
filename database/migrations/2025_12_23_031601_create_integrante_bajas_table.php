<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integrante_bajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integrante_id')->nullable();

            // Consejo al que pertenece la baja
            $table->foreignId('consejo_id')->constrained('consejos')
                ->onDelete('cascade');

            // Snapshot del integrante (clave para reportes)
            $table->string('nombre');
            $table->string('apellido');

            // Motivo de la baja
            $table->enum('motivo', [
                'inasistencia',
                'sancion',
                'fin_periodo',
                'renuncia',
                'error_registro'
            ]);

            // Fecha efectiva de la baja
            $table->date('fecha_baja');

            // Documento que respalda la baja
            $table->string('evidencia_pdf')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrante_bajas');
    }
};
