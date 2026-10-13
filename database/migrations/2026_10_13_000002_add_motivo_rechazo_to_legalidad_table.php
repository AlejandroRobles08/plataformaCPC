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
            // Motivo capturado por el administrador al no aprobar la reelección
            $table->text('motivo_rechazo')->nullable()->after('estatus_reeleccion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('legalidad', function (Blueprint $table) {
            $table->dropColumn('motivo_rechazo');
        });
    }
};
