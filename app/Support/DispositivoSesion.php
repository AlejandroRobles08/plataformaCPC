<?php

namespace App\Support;

// Convierte el user agent de una sesión en un texto legible, como "Windows · Chrome"
class DispositivoSesion
{
    // El orden importa: iOS incluye "Mac OS X" en su user agent y Android incluye "Linux"
    private const SISTEMAS = [
        'Windows' => '/Windows/i',
        'Android' => '/Android/i',
        'iOS' => '/iPhone|iPad|iPod/i',
        'macOS' => '/Macintosh|Mac OS X/i',
        'ChromeOS' => '/CrOS/i',
        'Linux' => '/Linux/i',
    ];

    // El orden importa: Edge, Opera y Samsung incluyen "Chrome", y Chrome incluye "Safari"
    private const NAVEGADORES = [
        'Edge' => '/Edg(e|A|iOS)?\//i',
        'Opera' => '/OPR\/|Opera/i',
        'Samsung Internet' => '/SamsungBrowser/i',
        'Firefox' => '/Firefox|FxiOS/i',
        'Chrome' => '/Chrome|CriOS/i',
        'Safari' => '/Safari/i',
    ];

    public static function nombre(?string $userAgent): string
    {
        $partes = array_filter([
            self::buscar(self::SISTEMAS, $userAgent),
            self::buscar(self::NAVEGADORES, $userAgent),
        ]);

        return $partes ? implode(' · ', $partes) : 'Dispositivo desconocido';
    }

    public static function esMovil(?string $userAgent): bool
    {
        return (bool) preg_match('/Android|iPhone|iPad|iPod|Mobile/i', (string) $userAgent);
    }

    private static function buscar(array $patrones, ?string $userAgent): ?string
    {
        foreach ($patrones as $nombre => $patron) {
            if (preg_match($patron, (string) $userAgent)) {
                return $nombre;
            }
        }

        return null;
    }
}
