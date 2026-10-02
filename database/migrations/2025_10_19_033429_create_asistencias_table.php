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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')
                ->nullable()
                ->constrained('sesiones')
                ->nullOnDelete();
            $table->foreignId('integrante_id')->constrained()->onDelete('cascade');
            $table->enum('tipo_sesion', ['ordinaria', 'solemne', 'extraordinaria']);
            $table->string('evidencia')->nullable();

            // Justificante de inasistencia y su estado administrativo
            $table->string('justificante')->nullable();
            $table->enum('estado_justificante', [
                'pendiente',
                'aprobado',
                'rechazado'
            ])->nullable();

            $table->date('fecha');
            $table->enum('estado', ['asistio', 'falto', 'justificada'])
                ->default('asistio');
            $table->timestamps();

            // Comentario del admin sobre el justificante
            $table->text('comentario_justificante')->nullable();
            // Fecha en que el integrante vio el comentario de rechazo (null = no visto)
            $table->timestamp('comentario_visto_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
