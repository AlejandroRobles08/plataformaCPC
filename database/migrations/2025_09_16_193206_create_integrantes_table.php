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
        Schema::create('integrantes', function (Blueprint $table) {
            $table->id();

            // Usuario con el que el integrante inicia sesión
            $table->foreignId('user_id')->unique()->constrained()
                ->cascadeOnUpdate()->restrictOnDelete();

            $table->string('nombre');
            $table->string('apellido');
            $table->string('genero')->nullable();
            $table->string('direccion', 500)->nullable();
            $table->string('discapacidad')->nullable();
            $table->string('discapacidad_tipo')->nullable();
            $table->string('puesto');
            $table->string('correo')->nullable()->unique();
            $table->foreignId('consejo_id')->constrained('consejos')
                ->onUpdate('cascade')->onDelete('restrict');
            $table->integer('formula')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integrantes');
    }
};
