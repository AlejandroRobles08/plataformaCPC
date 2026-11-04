<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consejos', function (Blueprint $table) {
            // Un consejo sin fecha de instalación se considera "No instalado"
            $table->date('instalado_at')->nullable()->after('descripcion');
            $table->string('acta_instalacion')->nullable()->after('instalado_at');
            $table->foreignId('instalado_por')->nullable()->after('acta_instalacion')
                ->constrained('users')->nullOnDelete();
        });

        // Se instalan los consejos que ya tienen al menos 10 fórmulas completas (2 integrantes cada una)
        $formulasCompletas = DB::table('integrantes')
            ->whereNotNull('formula')
            ->groupBy('consejo_id', 'formula')
            ->havingRaw('COUNT(*) >= 2')
            ->select('consejo_id');

        $instalados = DB::query()
            ->fromSub($formulasCompletas, 'completas')
            ->groupBy('consejo_id')
            ->havingRaw('COUNT(*) >= 10')
            ->pluck('consejo_id');

        DB::table('consejos')->whereIn('id', $instalados)->update([
            'instalado_at' => DB::raw('CURRENT_DATE'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consejos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instalado_por');
            $table->dropColumn(['instalado_at', 'acta_instalacion']);
        });
    }
};
