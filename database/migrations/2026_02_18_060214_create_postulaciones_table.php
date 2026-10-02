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
        Schema::create('postulaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('nombre');
            $table->string('apellidos');
            $table->string('genero')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('correo');
            $table->foreignId('consejo_id')->constrained()->onDelete('cascade');
            $table->string('puesto');
            $table->unsignedInteger('formula')->nullable();

            // Ruta de almacenamiento del documento de postulación
            $table->string('documento')->nullable();
            $table->string('acta_resolucion')->nullable();

            // Validación (flujo)
            $table->enum('estatus', ['pendiente', 'aprobada', 'no_aprobada'])
                ->default('pendiente');
            $table->foreignId('validado_por')->nullable()->constrained('users')
                ->nullOnDelete();

            // Fecha en la que se realizó la postulación
            $table->timestamp('fecha_postulacion')->nullable();
            // Fecha en la que se validó
            $table->timestamp('fecha_validacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postulaciones');
    }
};
