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
        Schema::create('sesiones', function (Blueprint $table) {
            $table->id();

            // Consejo al que pertenece la sesión
            $table->foreignId('consejo_id')
                ->constrained()
                ->cascadeOnDelete();

            // Fecha programada
            $table->date('fecha');

            // Tipo de sesión
            $table->enum('tipo_sesion', [
                'ordinaria',
                'solemne',
                'extraordinaria',
            ]);

            // Estado de la sesión
            $table->enum('estado', [
                'programada',
                'realizada',
                'cancelada',
            ])->default('programada');

            $table->timestamps();

            // Evita duplicar exactamente la misma sesión
            $table->unique([
                'consejo_id',
                'fecha',
                'tipo_sesion',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};