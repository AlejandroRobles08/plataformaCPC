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
        Schema::create('legalidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consejo_id')->constrained('consejos')->onDelete('cascade');
            $table->foreignId('integrante_id')->constrained('integrantes')->onDelete('cascade');
            $table->date('inicio_cargo');
            $table->date('fin_cargo');
            $table->string('periodo_habil');

            // Documentos de reelección
            $table->string('doc_nombramiento')->nullable();
            $table->string('doc_carta_reeleccion')->nullable();
            $table->string('doc_otros')->nullable();
            $table->date('fecha_inicio_reeleccion')->nullable();

            // Estatus de la solicitud
            $table->enum('estatus_reeleccion', [
                'pendiente',
                'aprobado',
                'rechazado'
            ])->default('pendiente');

            // Control de reelección (solo una vez)
            $table->boolean('ya_reelegido')->default(false);

            // Fecha en la que se validó
            $table->date('fecha_validacion')->nullable();

            $table->timestamps();

            // Usuario que validó
            $table->foreignId('validado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legalidad');
    }
};
