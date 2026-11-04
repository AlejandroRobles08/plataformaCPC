<?php

namespace Database\Seeders;

use App\Models\Consejo;
use App\Models\Integrante;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// TEMPORAL: llena un consejo con 15 fórmulas (30 integrantes) para probar las reglas
// de mínimo/máximo de fórmulas. Se elimina junto con sus registros al terminar las pruebas.
// Ejecutar con: php artisan db:seed --class=FormulasPruebaSeeder
class FormulasPruebaSeeder extends Seeder
{
    // Consejo 3: Centro Histórico y Patrimonio Edificado (vacío al crear el seeder)
    private const CONSEJO_ID = 3;
    public const DOMINIO = 'formulas.test';

    public function run(): void
    {
        $consejo = Consejo::findOrFail(self::CONSEJO_ID);

        // No mezclar con integrantes reales: solo se permiten los de este seeder
        $ajenos = Integrante::where('consejo_id', $consejo->id)
            ->where('correo', 'not like', '%@' . self::DOMINIO)
            ->count();

        if ($ajenos > 0) {
            throw new \RuntimeException(
                "El consejo {$consejo->nombre} ya tiene {$ajenos} integrante(s) que no son de prueba."
            );
        }

        DB::transaction(function () use ($consejo) {
            for ($formula = 1; $formula <= Integrante::MAX_FORMULAS; $formula++) {
                foreach (Integrante::CARGOS as $i => $cargo) {
                    $tipo = $i === 0 ? 'titular' : 'suplente';
                    $correo = "f{$formula}.{$tipo}@" . self::DOMINIO;
                    $nombre = 'Prueba F' . $formula;
                    $apellido = ucfirst($tipo);

                    $user = User::updateOrCreate(
                        ['email' => $correo],
                        [
                            'name' => "{$nombre} {$apellido}",
                            'password' => Hash::make('pass123'),
                            'must_change_password' => false,
                        ]
                    );
                    $user->syncRoles(['integrante']);

                    Integrante::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'nombre' => $nombre,
                            'apellido_paterno' => $apellido,
                            'cargo' => $cargo,
                            'correo' => $correo,
                            'genero' => $formula % 2 === 0 ? 'Hombre' : 'Mujer',
                            'discapacidad' => 'no',
                            'consejo_id' => $consejo->id,
                            'formula' => $formula,
                            'alta_completada_at' => now(),
                        ]
                    );
                }
            }
        });

        $this->command?->info("Consejo {$consejo->nombre}: 30 integrantes de prueba (contraseña: pass123).");
    }
}
