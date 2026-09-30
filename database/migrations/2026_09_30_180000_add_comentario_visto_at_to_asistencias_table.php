<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('asistencias', function (Blueprint $table) {
            // Fecha en que el integrante vio el comentario de rechazo (null = no visto)
            $table->timestamp('comentario_visto_at')->nullable()->after('comentario_justificante');
        });
    }

    public function down(): void {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('comentario_visto_at');
        });
    }
};
