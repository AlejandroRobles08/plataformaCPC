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
            $table->string('ruta')->nullable();

            // Validación del documento
            $table->enum('estatus', [
                'pendiente',
                'aprobado',
                'rechazado',
            ])->default('pendiente');
            $table->text('observacion')->nullable();
            $table->foreignId('validado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('validado_at')->nullable();

            $table->timestamps();

            $table->unique(['integrante_id', 'tipo']); // Evita duplicados por tipo
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
