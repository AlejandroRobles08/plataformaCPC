<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las postulaciones ya no capturan consejo, cargo ni fórmula;
     * el integrante creado al aprobar se queda sin ellos hasta que se le asignen.
     */
    public function up(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable()->change();
            $table->string('puesto')->nullable()->change();
        });

        Schema::table('integrantes', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable()->change();
            $table->string('puesto')->nullable()->change();
        });

        Schema::table('legalidad', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postulaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable(false)->change();
            $table->string('puesto')->nullable(false)->change();
        });

        Schema::table('integrantes', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable(false)->change();
            $table->string('puesto')->nullable(false)->change();
        });

        Schema::table('legalidad', function (Blueprint $table) {
            $table->unsignedBigInteger('consejo_id')->nullable(false)->change();
        });
    }
};
