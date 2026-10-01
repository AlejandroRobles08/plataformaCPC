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
        Schema::create('docus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integrante_id')->constrained()->onDelete('cascade');

            $table->string('tipo'); // ej: ine, comprobante_domicilio, curriculum_vitae, etc.
            $table->string('archivo'); // ruta del archivo almacenado
            $table->unique(['integrante_id', 'tipo']); // Evita duplicados por tipo

            //$table->string('tipo');
            $table->string('ruta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docus');
    }
};
  