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
        Schema::table('legalidad', function (Blueprint $table) {
            // Fecha de aprobación capturada al validar la reelección; inicia el segundo periodo
            $table->date('fecha_aprobacion')->nullable()->after('fecha_validacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legalidad', function (Blueprint $table) {
            $table->dropColumn('fecha_aprobacion');
        });
    }
};
