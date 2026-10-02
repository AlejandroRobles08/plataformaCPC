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
        Schema::create('convocatorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consejo_id')->constrained()->onDelete('cascade');
            $table->enum('tipo_sesion', ['ordinaria', 'solemne', 'extraordinaria']);
            $table->date('fecha')->nullable();

            // Documentos de la sesión
            $table->string('convocatoria_documento')->nullable();
            $table->string('documento')->nullable();
            $table->string('lista_asistencia')->nullable();
            $table->string('evidencia')->nullable();

            $table->boolean('estado_convocatoria')->default(false); // false = pendiente, true = realizada
            $table->boolean('estado_sesion')->default(true); // true = activa, false = inactiva
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convocatorias');
    }
};
