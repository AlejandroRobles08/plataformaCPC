<?php

namespace App\Http\Controllers;

use App\Models\Consejo;
use App\Models\Integrante;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        //El rol invitado no ve las gráficas, no se envían datos
        if (auth()->user()->hasRole('invitado')) {
            return Inertia::render('Dashboard');
        }

        //Personas totales por consejo
        $consejos = Consejo::withCount('integrantes')->get();

        $labels = $consejos->pluck('nombre');
        $data   = $consejos->pluck('integrantes_count');


        //conteo de genero por consejo
        $generoLabels = [
            'Mujer',
            'Hombre',
            'Prefiero autodescribirme',
            'Prefiero no responder',
        ];

        $generoData = [];
        foreach ($generoLabels as $label) {
            $generoData[$label] = [];
        }

        foreach ($consejos as $consejo) {

            $counts = [
                'Mujer' => 0,
                'Hombre' => 0,
                'Prefiero autodescribirme' => 0,
                'Prefiero no responder' => 0,
            ];

            foreach ($consejo->integrantes as $integrante) {

                $g = trim((string) $integrante->genero);
                $gl = mb_strtolower($g);

                if ($gl === 'mujer') {
                    $counts['Mujer']++;

                } elseif ($gl === 'hombre') {
                    $counts['Hombre']++;

                } elseif ($gl === 'prefiero no responder') {
                    $counts['Prefiero no responder']++;

                } elseif ($g === '' || $g === null) {
                    //el null lo tratamos como "Prefiero autodescribirme"
                    $counts['Prefiero autodescribirme']++;

                } else {
                    $counts['Prefiero autodescribirme']++;
                }
            }

            foreach ($generoLabels as $label) {
                $generoData[$label][] = $counts[$label];
            }
        }


        //conteo global de genero 
        $mujerCount = Integrante::whereRaw("LOWER(TRIM(genero)) = 'mujer'")->count();

        $hombreCount = Integrante::whereRaw("LOWER(TRIM(genero)) = 'hombre'")->count();

        $noResponderCount = Integrante::whereRaw("LOWER(TRIM(genero)) = 'prefiero no responder'")->count();

        $autodescritoCount = Integrante::whereNotNull('genero')
            ->whereRaw("TRIM(genero) <> ''")
            ->whereRaw("LOWER(TRIM(genero)) NOT IN ('mujer','hombre','prefiero no responder')")
            ->count();

        $generoTotales = [
            'Mujer' => $mujerCount,
            'Hombre' => $hombreCount,
            'Prefiero autodescribirme' => $autodescritoCount,
            'Prefiero no responder' => $noResponderCount,
        ];


        //conteo global de discapacidad
        $conDiscapacidad = Integrante::whereRaw("
            LOWER(TRIM(discapacidad)) IN ('si','sí')
        ")->count();

        $sinDiscapacidad = Integrante::where(function ($q) {
            $q->whereRaw("LOWER(TRIM(discapacidad)) = 'no'")
              ->orWhereNull('discapacidad')
              ->orWhere('discapacidad', '');
        })->count();

        $discapacidadTotales = [
            'Con discapacidad' => $conDiscapacidad,
            'Sin discapacidad' => $sinDiscapacidad,
        ];


        //Enviar datos a la vista Vue
        return Inertia::render('Dashboard', [
            'labels'               => $labels,
            'data'                 => $data,
            'generoLabels'         => $generoLabels,
            'generoData'           => $generoData,
            'generoTotales'        => $generoTotales,
            'discapacidadTotales'  => $discapacidadTotales,
        ]);
    }
}