<?php

namespace Tests\Unit;

use Tests\TestCase;

// Protege la base de desarrollo: los tests deben correr siempre en SQLite en memoria
class EntornoDePruebasTest extends TestCase
{
    public function test_los_tests_no_usan_la_base_de_datos_real(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
