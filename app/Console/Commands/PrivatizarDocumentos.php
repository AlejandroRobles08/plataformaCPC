<?php

namespace App\Console\Commands;

use App\Models\Docu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

// Mueve los documentos de integrantes del disco público (storage/app/public,
// accesible por /storage) al disco privado. Se puede ejecutar varias veces:
// los documentos que ya están en el disco privado se omiten.
class PrivatizarDocumentos extends Command
{
    protected $signature = 'documentos:privatizar {--dry-run : Solo muestra lo que se haría, sin mover archivos}';

    protected $description = 'Mueve los documentos de integrantes al almacenamiento privado';

    public function handle(): int
    {
        $publico = Storage::disk('public');
        $privado = Docu::disco();
        $simulacion = (bool) $this->option('dry-run');

        $movidos = 0;
        $omitidos = 0;
        $faltantes = [];

        Docu::query()->orderBy('id')->chunkById(100, function ($documentos) use (
            $publico, $privado, $simulacion, &$movidos, &$omitidos, &$faltantes
        ) {
            foreach ($documentos as $documento) {
                $origen = $documento->archivo;

                if ($privado->exists($origen)) {
                    $omitidos++;
                    continue;
                }

                if (! $publico->exists($origen)) {
                    $faltantes[] = [$documento->id, $documento->integrante_id, $documento->tipo, $origen];
                    continue;
                }

                $destino = Docu::carpetaDe($documento->integrante_id) . '/' . basename($origen);
                $this->line("[{$documento->id}] {$origen} → {$destino}");

                if ($simulacion) {
                    $movidos++;
                    continue;
                }

                $privado->put($destino, $publico->get($origen));
                $documento->update(['archivo' => $destino]);

                // Los archivos de postulaciones también los usa la postulación, se conservan
                if (str_starts_with($origen, 'documentos/')) {
                    $publico->delete($origen);
                }

                $movidos++;
            }
        });

        $this->newLine();
        $this->info(($simulacion ? '[simulación] ' : '') . "Movidos: {$movidos} · Ya privados: {$omitidos} · Sin archivo: " . count($faltantes));

        if ($faltantes) {
            $this->warn('Documentos registrados cuyo archivo no existe en ningún disco:');
            $this->table(['id', 'integrante', 'tipo', 'archivo'], $faltantes);
        }

        return self::SUCCESS;
    }
}
