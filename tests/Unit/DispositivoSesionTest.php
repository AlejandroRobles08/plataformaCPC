<?php

namespace Tests\Unit;

use App\Support\DispositivoSesion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DispositivoSesionTest extends TestCase
{
    public static function userAgents(): array
    {
        return [
            'Chrome en Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'Windows · Chrome', false],
            'Edge en Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'Windows · Edge', false],
            'Firefox en Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Linux · Firefox', false],
            'Safari en macOS' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15', 'macOS · Safari', false],
            'Safari en iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1', 'iOS · Safari', true],
            'Chrome en Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 'Android · Chrome', true],
            'Sin user agent' => [null, 'Dispositivo desconocido', false],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_describe_el_dispositivo(?string $userAgent, string $nombre, bool $movil): void
    {
        $this->assertSame($nombre, DispositivoSesion::nombre($userAgent));
        $this->assertSame($movil, DispositivoSesion::esMovil($userAgent));
    }
}
