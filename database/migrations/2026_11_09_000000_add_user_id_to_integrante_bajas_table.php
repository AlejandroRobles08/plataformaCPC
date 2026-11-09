<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Al dar de baja se borra el integrante pero no su usuario: guardar el user_id permite
// saber en el login que esa cuenta quedó desactivada. Las bajas anteriores quedan en null.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrante_bajas', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('integrante_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('integrante_bajas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
