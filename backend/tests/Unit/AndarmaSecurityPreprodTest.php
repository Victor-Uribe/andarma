<?php
// ANDARMA_FASE3_PREFLIGHT_V1
namespace Tests\Unit;

use App\Http\Controllers\Api\AndarmaAdminController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Prueba pura: no usa conexión ni altera la base de datos. */
class AndarmaSecurityPreprodTest extends TestCase
{
    public function test_rejects_dangerous_public_banner_urls(): void
    {
        $validate = new ReflectionMethod(AndarmaAdminController::class, 'safePublicUrl');
        foreach ([
            'javascript:alert(1)', 'data:text/html;base64,SGVsbG8=',
            '//malicioso.test/entrada', 'http://no-seguro.test/',
            'https://good.test/\nmalicioso', '\\evil.test',
        ] as $url) {
            self::assertFalse($validate->invoke(null, $url), 'URL peligrosa: '.$url);
        }
    }

    public function test_accepts_https_and_local_routes(): void
    {
        $validate = new ReflectionMethod(AndarmaAdminController::class, 'safePublicUrl');
        foreach ([null, '', '#contacto', '/desarrollos', '/desarrollos/rosario',
                  'https://andarma.mx/desarrollos'] as $url) {
            self::assertTrue($validate->invoke(null, $url), 'URL válida rechazada: '.(string) $url);
        }
    }
}
